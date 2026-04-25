<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Reservations extends Model
{
    use HasFactory, LogsActivity;
    protected $fillable = [
        'user_id',
        'room_id',
        'date',
        'day_of_week',
        'start_time',
        'end_time',
        'status',
        'reason'
    ];

    protected $casts = [
        'date' => 'date',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($reservation) {
            // Auto set day_of_week dari date
            if ($reservation->date) {
                $reservation->day_of_week = strtolower(Carbon::parse($reservation->date)->format('l'));
            }
        });

        static::updating(function ($reservation) {
            // Auto update day_of_week jika date berubah
            if ($reservation->isDirty('date')) {
                $reservation->day_of_week = strtolower(Carbon::parse($reservation->date)->format('l'));
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function room()
    {
        return $this->belongsTo(Rooms::class, 'room_id');
    }

    public function scopeSearch($query, $search)
    {
        if ($search) {
            return $query->where(function ($q) use ($search) {
                $q->where('date', 'like', "%{$search}%")
                    ->orWhere('day_of_week', 'like', "%{$search}%")
                    ->orWhere('start_time', 'like', "%{$search}%")
                    ->orWhere('end_time', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('room', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }
        return $query;
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
    public function getStatusLabelAttribute()
    {
        $labels = [
            'pending' => 'Menunggu',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'canceled' => 'Dibatalkan',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    public function getStartTimeAttribute()
    {
        return $this->attributes['start_time'] ?? null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('reservation')
            ->logFillable()
            ->logOnlyDirty()
            // ->setDescriptionForEvent(fn(string $eventName) => "Reservasi telah {$eventName}");
            ->setDescriptionForEvent(function(string $eventName){
                return "Reservation has {$eventName}";
            });
    }
}
