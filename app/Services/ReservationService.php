<?php

namespace App\Services;

use App\Models\Reservations;
use Illuminate\Http\Request;

class ReservationService
{
    public function filterReservations(array $filters,)
    {
        $query = Reservations::with(['user', 'room']);
        
        if (!empty($filters['date'])) {
            $query->where('date', $filters['date']);
        }
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (!empty($filters['room_id'])) {
            $query->where('room_id', $filters['room_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['user_name'])) {
            $query->whereHas('user', function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['user_name'] . '%');
            });
        }
        if (!empty($filters['room_name'])) {
            $query->whereHas('room', function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['room_name'] . '%');
            });
        }
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'asc';
        $allowedSorts = ['created_at', 'date', 'start_time', 'end_time', 'status'];

        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'created_at';
        }

        $sortOrder = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $filters['per_page'] ?? 10;
        if ($perPage === 'all') {
            return $query->get();
        }
        return $query->paginate((int) $perPage);
    }
}

