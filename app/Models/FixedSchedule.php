<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FixedSchedule extends Model
{
    use HasFactory;

    protected $table = 'fixed_schedule';

    protected $fillable = [
        'room_id',
        'day_of_week',
        'start_time',
        'end_time',
        'description',
    ];

    public function room()
    {
        return $this->belongsTo(Rooms::class);
    }
    public function isConflict($dayOfWeek, $startTime, $endTime)
    {
        if ($this->day_of_week !== $dayOfWeek) {
            return false;
        }

        return !($endTime <= $this->start_time || $startTime >= $this->end_time);
    }

    public function scopeByDay($query, $dayOfWeek)
    {
        return $query->where('day_of_week', $dayOfWeek);
    }
    public function scopeByRoom($query, $roomId)
    {
        return $query->where('room_id', $roomId);
    }


    public function getDayLabelAttribute()
    {
        $days = [
            'monday' => 'Senin',
            'tuesday' => 'Selasa',
            'wednesday' => 'Rabu',
            'thursday' => 'Kamis',
            'friday' => 'Jumat',
            'saturday' => 'Sabtu',
            'sunday' => 'Minggu',
        ];

        return $days[$this->day_of_week] ?? $this->day_of_week;
    }

    public function scopeSearch($query, $search)
    {
        if ($search) {
            return $query->where(function ($q) use ($search) {
                $q->where('day_of_week', 'like', "%{$search}%")
                    ->orWhere('start_time', 'like', "%{$search}%")
                    ->orWhere('end_time', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('room', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }
        return $query;
    }
}
