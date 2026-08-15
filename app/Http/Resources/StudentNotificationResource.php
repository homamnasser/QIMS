<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentNotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            // قد تكون null: بطاقة بلا نقرة أهون من نقرة تفتح مساراً لا وجود له.
            'route' => $this->route,
            'is_read' => $this->read_at !== null,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
