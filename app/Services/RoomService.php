<?php

namespace App\Services;

use App\Models\Rooms;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RoomService
{
    public function storeRoom(array $data)
    {
        DB::beginTransaction();
        try {
            $room = Rooms::create([
                'name' => $data['name'],
                'capacity' => $data['capacity'],
                'description' => $data['description'],
                'status' => $data['status'] ?? 'inactive',
            ]);

            DB::commit();
            return $room;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("Service Error [storeRoom]: " . $e->getMessage());

            throw $e;
        }
    }

    public function showRoomById(string $id)
    {
        try {
            $room = Rooms::find($id);

            if (!$room) {
                throw new \Exception('Room tidak ditemukan', 404);
            }

            return $room;
        } catch (\Exception $e) {
            Log::error("Service Error [showRoomById]: " . $e->getMessage());

            throw $e;
        }
    }

    public function updateRoom(string $id, array $data)
    {
        DB::beginTransaction();
        try {
            $room = Rooms::find($id);

            if (!$room) {
                throw new \Exception('Room tidak ditemukan', 404);
            }

            if ($room->status === 'active') {
                throw new \Exception('Ruangan tidak dapat diubah karena masih dalam status aktif', 400);
            }

            $room->update($data);

            DB::commit();
            return $room;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("Service Error [updateRoom]: " . $e->getMessage());

            throw $e;
        }
    }

    public function deleteRoom(string $id)
    {
        DB::beginTransaction();
        try {
            $room = Rooms::find($id);

            if (!$room) {
                throw new \Exception('Room tidak ditemukan', 404);
            }

            if ($room->reservations()->where('status', 'active')->exists()) {
                throw new \Exception('Ruangan tidak bisa dihapus karena masih ada reservasi aktif', 400);
            }

            if ($room->fixedSchedules()->exists()) {
                throw new \Exception('Ruangan tidak bisa dihapus karena masih ada jadwal rutin', 400);
            }

            $room->delete();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("Service Error [deleteRoom]: " . $e->getMessage());

            throw $e;
        }
    }
}
