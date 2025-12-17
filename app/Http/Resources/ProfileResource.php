<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'balance' => (string) $this->balance,
            'assets' => AssetResource::collection($this->assets),
            'open_orders' => OrderResource::collection(
                $this->orders()->where('status', OrderStatus::OPEN)->get()
            ),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
