<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemorizationResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        return [
            'memorization_id' => $this->id,
            'page_number'     => $this->page_number,
            'name'            => $this->name,

            'student' => [
                'id'        => $this->studentDetails ? $this->studentDetails->id : null,
                'full_name' => $this->studentDetails
                    ? trim($this->studentDetails->first_name . ' ' . $this->studentDetails->last_name)
                    : null,
            ],

            'giver' => [
                'id'        => $this->giverDetails ? $this->giverDetails->id : null,
                'full_name' => $this->giverDetails
                    ? trim($this->giverDetails->first_name . ' ' . $this->giverDetails->last_name)
                    : null,
            ],

            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
