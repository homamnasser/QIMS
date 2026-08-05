<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NoteResource extends JsonResource
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
            'note_id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,

            'student' => [
                'id' => $this->student ? $this->student->id : null,
                'selfnumber' => $this->student?->selfnumber,
                'full_name' => $this->student
                    ? ($this->student->first_name.' '.$this->student->last_name)
                    : null,
            ],

            'teacher' => [
                'id' => $this->author ? $this->author->id : null,
                'full_name' => $this->author->first_name && $this->author->last_name
                    ? ($this->author->first_name.' '.$this->author->last_name)
                    : null,
            ],

            'author' => [
                'id' => $this->author ? $this->author->id : null,
                'first_name' => $this->author ? $this->author->first_name : null,
                'last_name' => $this->author ? $this->author->last_name : null,
            ],

            'occurred_at' => ($this->occurred_at ?? $this->created_at)?->format('Y-m-d'),
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
