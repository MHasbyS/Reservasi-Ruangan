<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FixedSchedule;
use App\Models\Reservations;
use App\Models\Rooms;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Requests\GetReservationRequest;
use App\Helpers\ApiResponse;
use App\Http\Resources\PaginatedResource;
use App\Http\Resources\ReservationApprovalResource;
use App\Http\Resources\ReservationResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Mail\ReservationNotificationMail;
use App\Services\ReservationService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use App\Exports\ReservationsExport;
use Maatwebsite\Excel\Facades\Excel;

class ReservationController extends Controller
{
    // Controller
    public function export()
    {
        return Excel::download(new ReservationsExport, 'reservations.xlsx');
    }

    public function exportExcel(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $validated['start_date'] ?? null;
        $endDate   = $validated['end_date'] ?? null;

        $fileName = 'reservations_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

         return Excel::download(new ReservationsExport($startDate, $endDate), $fileName);
    }
    protected $reservationService;

    public function __construct(ReservationService $reservationService)
    {
        $this->reservationService = $reservationService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(GetReservationRequest $request)
    {
        $reservations = Reservations::search($request->search)->latest()->paginate($request->limit ?? 10);

        return ApiResponse::success(
            new PaginatedResource($reservations, ReservationResource::class),
            "List Reservasi"
        );
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreReservationRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $date = Carbon::parse($validated['date']);
            $dayOfWeek = strtolower($date->format('l'));

            $room = Rooms::find($validated['room_id']);
            if (!$room) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ruangan tidak ditemukan'
                ], 404);
            }

            $fixedConflict = FixedSchedule::where('room_id', $validated['room_id'])
                ->where('day_of_week', $dayOfWeek)
                ->whereRaw('? < end_time AND ? > start_time', [
                    $validated['start_time'],
                    $validated['end_time']
                ])
                ->first();

            if ($fixedConflict) {
                $reservation = Reservations::create([
                    'user_id' => Auth::id(),
                    'room_id' => $validated['room_id'],
                    'date' => $validated['date'],
                    'start_time' => $validated['start_time'],
                    'end_time' => $validated['end_time'],
                    'status' => 'rejected',
                    'reason' => "Otomatis ditolak: Bentrok dengan jadwal tetap ({$fixedConflict->description}) pada hari {$fixedConflict->day_label} pukul " . date('H:i', strtotime($fixedConflict->start_time)) . "-" . date('H:i', strtotime($fixedConflict->end_time))
                ]);

                activity('reservation')
                    ->causedBy(Auth::user())
                    ->performedOn($reservation)
                    ->withProperties([
                        'date' => $reservation->date,
                        'start' => $reservation->start_time,
                        'end' => $reservation->end_time,
                    ])
                    ->log('Reservasi baru dibuat dan menunggu persetujuan.');

                return response()->json([
                    'success' => false,
                    'message' => 'Reservasi otomatis ditolak karena bentrok dengan jadwal tetap',
                    'data' => new ReservationResource($reservation->load(['user', 'room']))
                ], 400);
            }

            $reservationConflict = Reservations::where('room_id', $validated['room_id'])
                ->where('date', $validated['date'])
                ->where('status', 'approved')
                ->whereRaw('? < end_time AND ? > start_time', [
                    $validated['start_time'],
                    $validated['end_time']
                ])
                ->exists();

            if ($reservationConflict) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ruangan sudah direservasi pada waktu tersebut'
                ], 400);
            }

            $reservation = Reservations::create([
                'user_id' => Auth::id(),
                'room_id' => $validated['room_id'],
                'date' => $validated['date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'status' => 'pending'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Reservasi berhasil dibuat dan menunggu persetujuan',
                'data' => new ReservationResource($reservation->load(['user', 'room']))
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat reservasi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = Auth::user();

        $reservation = Reservations::with('room')->find($id);

        if (!$reservation) {
            return response()->json([
                'success' => false,
                'message' => 'Reservasi tidak ditemukan'
            ], 404);
        }

        if ($user->hasRole('karyawan') && $reservation->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki izin untuk melihat reservasi ini'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => new ReservationResource($reservation)
        ], 200);
    }

    public function approve(Request $request, $id)
    {
        try {
            $request->validate([
                'reason' => 'nullable|string|max:255',
            ]);

            $reservation = Reservations::findOrFail($id);

            if (in_array($reservation->status, ['canceled', 'rejected'])) {
                return response()->json([
                    'message' => 'Reservasi yang sudah dibatalkan atau ditolak tidak bisa di-approve lagi.'
                ], 422);
            }

            $oldStatus = $reservation->status;

            $reservation->update([
                'status' => 'approved',
                'reason' => $request->input('reason'),
            ]);

            $room = Rooms::find($reservation->room_id);
            if ($room) {
                $room->update(['status' => 'active']);
            }

            Reservations::where('room_id', $reservation->room_id)
                ->where('id', '!=', $reservation->id)
                ->where('status', 'pending')
                ->where(function ($query) use ($reservation) {
                    $query->whereBetween('start_time', [$reservation->start_time, $reservation->end_time])
                        ->orWhereBetween('end_time', [$reservation->start_time, $reservation->end_time])
                        ->orWhere(function ($q) use ($reservation) {
                            $q->where('start_time', '<=', $reservation->start_time)
                                ->where('end_time', '>=', $reservation->end_time);
                        });
                })
                ->update([
                    'status' => 'rejected',
                    'reason' => 'Ditolak otomatis karena jadwal sudah diambil reservasi lain.',
                ]);

            activity('reservation')
                ->causedBy(Auth::user())
                ->performedOn($reservation)
                ->event('approved')
                ->withProperties([
                    'old_status' => $oldStatus,
                    'new_status' => $reservation->status,
                ])
                ->log("Status reservasi diubah dari {$oldStatus} menjadi {$reservation->status}");

            // Mail::to($reservation->user->email)->send(new ReservationNotificationMail($reservation, 'approved'));

            return response()->json([
                'success' => true,
                'mesage' => 'Reservasi berhasil di-approve.',
                'data' => new ReservationApprovalResource($reservation),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'gagal approve reservasi.',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function reject(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $reservation = Reservations::findOrFail($id);

        $oldStatus = $reservation->status;
        $reservation->update([
            'status' => 'rejected',
            'reason' => $request->reason,
        ]);

        // Mail::to($reservation->user->email)->send(new ReservationNotificationMail($reservation, 'rejected'));

        $room = Rooms::find($reservation->room_id);
        if ($room) {
            $room->update(['status' => 'inactive',]);
        }

        activity('reservation')
            ->causedBy(Auth::user())
            ->performedOn($reservation)
            ->event('rejected')
            ->withProperties([
                'old_status' => $oldStatus,
                'new_status' => $reservation->status,
            ])
            ->log("Status reservasi diubah dari {$oldStatus} menjadi {$reservation->status}");

        return response()->json([
            'success' => true,
            'message' => 'Reservasi berhasil ditolak.',
            'data' => new ReservationApprovalResource($reservation),
        ], 200);
    }
}
