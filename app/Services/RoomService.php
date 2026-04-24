<?php

namespace App\Services;

use App\Models\Rooms;

class RoomService
{
    public function filterRooms(array $filters)
    {
        $query = Rooms::query();

        if (!empty($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['capacity'])) {
            $query->where('capacity', '>=', $filters['capacity']);
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = strtolower($filters['sort_order'] ?? 'asc');
        $allowedSorts = ['created_at', 'name', 'capacity', 'status'];

        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'created_at';
        }
        $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');

        $perPage = $filters['per_page'] ?? 5;

        if ($perPage === 'all') {
            return $query->get();
        }

        return $query->paginate((int)$perPage);
    }
}
