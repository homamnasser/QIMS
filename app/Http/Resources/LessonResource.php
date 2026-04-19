<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'pages' => [
                'start' => $this->start_page,
                'end' => $this->end_page,
                'total' => ($this->end_page && $this->start_page) ? ($this->end_page - $this->start_page + 1) : 0,
            ],
            'subject' =>  $this->subject?->name,

        ];
    }
}
