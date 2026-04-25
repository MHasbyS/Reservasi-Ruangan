<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFixedScheduleRequest;
use App\Http\Requests\UpdateFixedScheduleRequest;
use App\Http\Resources\FixedScheduleResource;
use App\Models\FixedSchedule;
use App\Models\Rooms;
use App\Services\FixedScheduleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Helpers\ApiResponse;
use App\Http\Resources\PaginatedResource;

class FixedScheduleController extends Controller
{
    protected $fixedScheduleService;

    public function __construct(FixedScheduleService $fixedScheduleService)
    {
        $this->fixedScheduleService = $fixedScheduleService;
    }
    public function index(Request $request)
    {
        $fixedSchedules = FixedSchedule::search($request->search)->orderBy('id', 'desc')->paginate($request->limit);
        return ApiResponse::success(
            new PaginatedResource($fixedSchedules, FixedScheduleResource::class),
            'List Fixed Schedules'
        );
    }


    public function getByRoom($roomId)
    {
        try {
            $fixedSchedule = FixedSchedule::where('room_id', $roomId)
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get();

            return response()->json([
                'status' => true,
                'data' => FixedScheduleResource::collection($fixedSchedule)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve schedules',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFixedScheduleRequest $request)
    {
        try {
            DB::beginTransaction();
            $validated = $request->validated();

            $room = Rooms::find($validated['room_id']);
            if (!$room) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ruangan tidak ditemukan'
                ], 404);
            }

            // Cek konflik dengan jadwal tetap lain di room yang sama
            $conflict = FixedSchedule::where('room_id', $validated['room_id'])
                ->where('day_of_week', $validated['day_of_week'])
                ->where(function ($query) use ($validated) {
                    $query->where(function ($q) use ($validated) {
                        $q->where('start_time', '<', $validated['end_time'])
                            ->where('end_time', '>', $validated['start_time']);
                    });
                })
                ->exists();

            if ($conflict) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jadwal rutin bertabrakan dengan jadwal rutin lain di ruangan yang sama',
                ], 400);
            }
            $fixedSchedule = FixedSchedule::create($validated);

            if ($fixedSchedule->room) {
                $fixedSchedule->room->update(['status' => 'active']);
            }

            DB::commit();
            return response()->json([
                'status' => true,
                'data' => new FixedScheduleResource($fixedSchedule->load('room')),
                'message' => 'Jadwal rutin berhasil dibuat'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Gagal membuat jadwal rutin',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $fixedSchedule = FixedSchedule::with('room')->findOrFail($id);

            if (!$fixedSchedule) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jadwal rutin tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => new FixedScheduleResource($fixedSchedule)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve schedule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFixedScheduleRequest $request, string $id)
    {
        try {
            DB::beginTransaction();

            $schedule = FixedSchedule::findOrFail($id);
            $validated = $request->validated();

            $oldRoomId = $schedule->room_id;

            if (!$schedule) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jadwal rutin tidak ditemukan'
                ], 404);
            }

            $conflict = FixedSchedule::where('room_id', $validated['room_id'] ?? $schedule->room_id)
                ->where('day_of_week', $validated['day_of_week'] ?? $schedule->day_of_week)
                ->where('id', '!=', $id)
                ->where(function ($query) use ($validated, $schedule) {
                    $startTime = $validated['start_time'] ?? $schedule->start_time;
                    $endTime = $validated['end_time'] ?? $schedule->end_time;

                    $query->where(function ($q) use ($startTime, $endTime) {
                        $q->where('start_time', '<', $endTime)
                            ->where('end_time', '>', $startTime);
                    });
                })
                ->exists();

            if ($conflict) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jadwal bentrok dengan jadwal tetap lain'
                ], 400);
            }

            $schedule->update($validated);

            if (isset($validated['room_id']) && $validated['room_id'] != $oldRoomId) {

                // Ruang lama jadi inactive jika tidak ada jadwal aktif lain
                $oldRoom = Rooms::find($oldRoomId);
                if ($oldRoom) {
                    $stillUsed = FixedSchedule::where('room_id', $oldRoomId)->exists();
                    if (!$stillUsed) {
                        $oldRoom->update(['status' => 'inactive']);
                    }
                }

                // Ruang baru jadi active
                $newRoom = Rooms::find($validated['room_id']);
                if ($newRoom) {
                    $newRoom->update(['status' => 'active']);
                }
            }

            DB::commit();

            $schedule->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Jadwal rutin berhasil diperbarui',
                'data' => new FixedScheduleResource($schedule->load('room')),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Gagal memperbarui jadwal rutin',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            DB::beginTransaction();
            $schedule = FixedSchedule::findOrFail($id);

            if (!$schedule) {
                return response()->json([
                    'status' => false,
                    'message' => 'Jadwal rutin tidak ditemukan'
                ], 404);
            }

            $schedule->delete();

            DB::commit();
            return response()->json([
                'Success' => true,
                'message' => 'Jadwal rutin berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Gagal menghapus jadwal rutin',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
