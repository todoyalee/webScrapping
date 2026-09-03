<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'price' => $this->price !== null ? (float) $this->price : null,
            'image_url' => $this->image_url,
            'source_url' => $this->source_url,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
