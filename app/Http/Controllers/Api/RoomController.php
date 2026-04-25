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
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRoomRequest $request)
    {
        try {
            $room = $this->roomService->storeRoom($request->validated());

            return ApiResponse::success(
                new RoomResource($room),
                'Berhasil menambahkan ruangan',
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Terjadi kesalahan pada server saat proses menambahkan ruangan',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                $e->getMessage()
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $room = $this->roomService->showRoomById($id);

            return ApiResponse::success(
                new RoomResource($room),
                'Berhasil memanggil detail ruangan',
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Terjadi kesalahan pada server saat proses memanggil detail ruangan',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                $e->getMessage()
            );
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRoomRequest $request, string $id)
    {
        try {
            $room = $this->roomService->updateRoom($id, $request->validated());

            return ApiResponse::success(
                new RoomResource($room),
                'Berhasil mengubah ruangan',
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Terjadi kesalahan pada server saat proses mengubah ruangan',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                $e->getMessage()
            );
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $this->roomService->deleteRoom($id);

            return ApiResponse::success(
                null,
                'Berhasil menghapus ruangan',
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Terjadi kesalahan pada server saat proses menghapus ruangan',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                $e->getMessage()
            );
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
