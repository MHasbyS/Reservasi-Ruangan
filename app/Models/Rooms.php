<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Rooms extends Model
{
    use HasFactory,LogsActivity;
    protected $fillable = [
        'name',
        'capacity',
        'description',
        'status'
    ];

    protected $casts = [
        'capacity' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'inactive',
    ];
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($room) {
            if ($room->isActive()) {
                throw new \Exception('Ruangan aktif tidak dapat dihapus');
            }
        });
    }

    public function reservations()
    {
        return $this->hasMany(Reservations::class, 'room_id');
    }

    public function fixedSchedules()
    {
        return $this->hasMany(FixedSchedule::class, 'room_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('room')
            ->logFillable()
            ->logOnlyDirty()
            // ->setDescriptionForEvent(fn(string $eventName) => "Reservasi telah {$eventName}");
            ->setDescriptionForEvent(function(string $eventName){
                return "Room has been {$eventName}";
            });
    }
    public function scopeSearch($query, $search)
    {
        return $query->when($search, function ($query, $search){
            $query->where('name', 'like', "%{$search}%");
        });
    }
}
