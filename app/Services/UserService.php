<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserService
{
    public function storeUser(array $data)
    {
        DB::beginTransaction();
        try {
            $data['password'] = bcrypt($data['password']);

            $user = User::create($data);

            if (isset($data['role'])) {
                $user->assignRole($data['role']);
            }

            DB::commit();
            return $user;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("Service Error [storeUser]: " . $e->getMessage());

            throw $e;
        }
    }

    public function showUserById(string $id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                throw new \Exception('User tidak ditemukan', 404);
            }

            return $user;
        } catch (\Exception $e) {
            Log::error("Service Error [showUserById]: " . $e->getMessage());

            throw $e;
        }
    }

    public function updateUser(string $id, array $data)
    {
        DB::beginTransaction();
        try {
            $user = User::find($id);

            if (!$user) {
                throw new \Exception('User tidak ditemukan', 404);
            }

            if (!empty($data['password'])) {
                $data['password'] = bcrypt($data['password']);
            } else {
                unset($data['password']);
            }

            $user->update($data);

            if (isset($data['role'])) {
                $user->assignRole($data['role']);
            }

            DB::commit();
            return $user;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("Service Error [updateUser]: " . $e->getMessage());

            throw $e;
        }
    }

    public function deleteUser(string $id)
    {
        DB::beginTransaction();
        try {
            $user = User::find($id);

            if (!$user) {
                throw new \Exception('User tidak ditemukan', 404);
            }

            if ($user->reservations()->whereIn('status', ['active', 'in_use'])->exists()) {
                throw new \Exception('User tidak bisa dihapus karena masih memiliki reservasi aktif atau sedang menggunakan ruangan', 400);
            }

            $user->delete();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("Service Error [deleteUser]: " . $e->getMessage());

            throw $e;
        }
    }
}
