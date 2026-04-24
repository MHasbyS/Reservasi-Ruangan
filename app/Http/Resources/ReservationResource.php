<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class ReservationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
            'room' => [
                'id' => $this->room->id,
                'name' => $this->room->name,
            ],
            'date' => $this->date,
            'day_of_week' => $this->day_of_week,
            'start_time' => Carbon::parse($this->start_time)->format('H:i:s'),
            'end_time' => Carbon::parse($this->end_time)->format('H:i:s'),
            'status' => $this->status,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
