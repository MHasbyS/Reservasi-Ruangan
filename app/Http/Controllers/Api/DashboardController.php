<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservations;
use App\Models\User;
use App\Models\Rooms;
use App\Models\FixedSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index():jsonResponse
    {
        $totalReservasi = Reservations::count();
        $totalRuangan = Rooms::count();
        $totalSchedule = FixedSchedule::count();
        $totalUsers = User::count();

        $monthlyReservations = Reservations::whereMonth('created_at', now()->month)->count();

        $chartData = Reservations::selectRaw('MONTH(created_at) as month, COUNT(*) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'bulan' => date('M', mktime(0, 0, 0, $item->month, 1)),
                    'jumlah' => $item->total,
                ];
            });
        return response()->json([
    'status' => true,
    'data' => [
        'total_reservasi' => $totalReservasi,
        'total_ruangan' => $totalRuangan,
        'total_schedule' => $totalSchedule,
        'total_user' => $totalUsers,
        'monthly_reservation' => $monthlyReservations,
        'chart_data' => $chartData
    ],
    ],200);
    }
}
