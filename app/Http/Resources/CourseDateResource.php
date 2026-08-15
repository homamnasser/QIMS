<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseDateResource extends JsonResource
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
            'session_date' => $this->session_date->format('Y-m-d'),
            'day_name' => Carbon::parse($this->session_date)->locale(app()->getLocale())->translatedFormat('l'),
            'status' => $this->status,
            'counts_for_attendance' => $this->counts_for_attendance,
            'held_at' => $this->held_at,
            'cancellation_reason' => $this->cancellation_reason,
        ];
    }
}
