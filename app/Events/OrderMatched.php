<?php

namespace App\Events;

use App\Models\Trade;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderMatched implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The trade that was created.
     */
    public Trade $trade;

    /**
     * The buyer's user ID.
     */
    public int $buyerId;

    /**
     * The seller's user ID.
     */
    public int $sellerId;

    /**
     * Create a new event instance.
     */
    public function __construct(Trade $trade, int $buyerId, int $sellerId)
    {
        $this->trade = $trade;
        $this->buyerId = $buyerId;
        $this->sellerId = $sellerId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->buyerId}"),
            new PrivateChannel("user.{$this->sellerId}"),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'trade' => [
                'id' => $this->trade->id,
                'symbol' => $this->trade->symbol,
                'price' => $this->trade->price,
                'amount' => $this->trade->amount,
                'commission' => $this->trade->commission,
                'executed_at' => $this->trade->created_at->toIso8601String(),
            ],
            'buyer_id' => $this->buyerId,
            'seller_id' => $this->sellerId,
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'order.matched';
    }
}
