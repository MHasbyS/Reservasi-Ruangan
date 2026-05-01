<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FixedSchedule;
use App\Models\Reservations;
use App\Models\Rooms;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Requests\GetReservationRequest;
use App\Helpers\ApiResponse;
use App\Http\Requests\GetRoomRequest;
use App\Http\Resources\PaginatedResource;
use App\Http\Resources\ReservationResource;
use App\Http\Resources\RoomResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class KaryawanController extends Controller
{
    /**
     * Display a listing of rooms for karyawan dashboard.
     */
    public function index(GetRoomRequest $request)
    {
        $rooms = Rooms::search($request->search)->latest()->paginate($request->limit ?? 10);

        return ApiResponse::success(
            new PaginatedResource($rooms, RoomResource::class),
            "List Ruangan"
        );
    }

    /**
     * Display a listing of reservations for the authenticated karyawan.
     */
    public function riwayat(GetReservationRequest $request)
    {
        $reservations = Reservations::where('user_id', Auth::id())
            ->search($request->search)
            ->latest()
            ->paginate($request->limit ?? 10);

        return ApiResponse::success(
            new PaginatedResource($reservations, ReservationResource::class),
            "List Reservasi Karyawan"
        );
    }

    /**
     * Store a newly created reservation for the authenticated karyawan.
     */
    public function store(StoreReservationRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $validated = $request->validated();

            $date = Carbon::parse($validated['date']);
            $dayOfWeek = strtolower($date->format('l'));

            $room = Rooms::find($validated['room_id']);
            if (!$room) {
                DB::rollBack();
                return ApiResponse::error('Ruangan tidak ditemukan', 404);
            }

            // Check conflict with fixed schedules
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
                    ->log('Reservasi baru dibuat dan otomatis ditolak karena bentrok jadwal tetap.');

                DB::commit();

                return response()->json([
                    'success' => false,
                    'message' => 'Reservasi otomatis ditolak karena bentrok dengan jadwal tetap',
                    'data' => new ReservationResource($reservation->load(['user', 'room']))
                ], 400);
            }

            // Check conflict with approved reservations
            $reservationConflict = Reservations::where('room_id', $validated['room_id'])
                ->where('date', $validated['date'])
                ->where('status', 'approved')
                ->whereRaw('? < end_time AND ? > start_time', [
                    $validated['start_time'],
                    $validated['end_time']
                ])
                ->exists();

            if ($reservationConflict) {
                DB::rollBack();
                return ApiResponse::error('Ruangan sudah direservasi pada waktu tersebut', 400);
            }

            $reservation = Reservations::create([
                'user_id' => Auth::id(),
                'room_id' => $validated['room_id'],
                'date' => $validated['date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'status' => 'pending'
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

            DB::commit();

            return ApiResponse::success(
                new ReservationResource($reservation->load(['user', 'room'])),
                'Reservasi berhasil dibuat dan menunggu persetujuan',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal membuat reservasi karyawan: ' . $e->getMessage());

            return ApiResponse::error('Gagal membuat reservasi', 500);
        }
    }
    public function cancel(Request $request, $id)
    {
        $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        $reservation = Reservations::findOrFail($id);

        if (!in_array($reservation->status, ['pending', 'approved'])) {
            return response()->json([
                'message' => 'Reservasi tidak bisa dibatalkan.',
            ], 422);
        }

        $reservation->update([
            'status' => 'canceled',
            'reason' => $request->reason,
        ]);

        activity('reservation')
            ->causedBy(Auth::user())
            ->performedOn($reservation)
            ->event('canceled')
            ->log('Reservasi dibatalkan oleh pengguna.');

        // Mail::to('admin@example.com')->send(new ReservationNotificationMail($reservation, 'canceled'));

        return response()->json([
            'success' => true,
            'message' => 'Reservasi berhasil dibatalkan.',
            'data' => new ReservationApprovalResource($reservation),
        ], 200);
    }
}
