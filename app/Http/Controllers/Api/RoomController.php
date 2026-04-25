<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Http\Requests\GetRoomRequest;
use Illuminate\Http\Request;
use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Http\Resources\PaginatedResource;
use App\Http\Resources\RoomResource;
use App\Models\Rooms;
use App\Services\RoomService;

class RoomController extends Controller
{
    protected $roomService;

    public function __construct(RoomService $roomService)
    {
        $this->roomService = $roomService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(GetRoomRequest $request)
    {
        $rooms = Rooms::search($request->search)->latest()->paginate($request->limit ?? 10);

        return ApiResponse::success(
            new PaginatedResource($rooms, RoomResource::class),
            'Berhasil memanggil list ruangan',
            200
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRoomRequest $request)
    {
        try {
            $validated = $request->validated();
            $room = Rooms::create([
                'name' => $validated['name'],
                'capacity' => $validated['capacity'],
                'description' => $validated['description'] ?? null,
                'status' => 'inactive',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Room Created Successfully',
                'data' => new RoomResource($room),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Room Creation Failed: ',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $room = Rooms::find($id);

            if (!$room) {
                return response()->json([
                    'success' => false,
                    'message' => 'Room not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new RoomResource($room),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve room: ',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRoomRequest $request, string $id)
    {
        try {
            $room = Rooms::find($id);

            if (!$room) {
                return response()->json([
                    'success' => false,
                    'message' => 'Room not found'
                ], 404);
            }

            if ($room->status === 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Ruangan tidak dapat diubah karena masih dalam status aktif'
                ], 400);
            }

            $room->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Room updated successfully',
                'data' => new RoomResource($room),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Room Update Failed: ',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Rooms $room, $id)
    {
        try {
            $room = Rooms::find($id);

            // if ($room->status === 'active') {
            //     return response()->json([
            //         'message' => 'Ruangan tidak bisa di hapus karena masih aktif.'
            //     ], 400);
            // }

            if ($room->reservations()->where('status', 'active')->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ruangan tidak bisa dihapus karena masih ada reservasi aktif.'
                ], 400);
            }

            if ($room->fixedSchedules()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ruangan tidak bisa di hapus karena masih ada jadwal rutin.'
                ], 400);
            }

            if (!$room) {
                return response()->json([
                    'success' => false,
                    'message' => 'Room not found'
                ], 404);
            }


            $room->delete();

            return response()->json([
                'success' => true,
                'message' => 'Room deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus ruangan',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getActiveRooms()
    {
        try {
            $rooms = Rooms::where('status', 'active')
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => RoomResource::collection($rooms)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data ruangan aktif',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
