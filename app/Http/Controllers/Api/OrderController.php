<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {
        //$this->middleware('auth:sanctum')->except(['index']);
    }

    /**
     * Get orderbook or all open orders for a symbol.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'symbol' => 'required|string',
            'side' => 'nullable|string|in:buy,sell',
        ]);

        $orders = $this->orderService->getOrderbook(
            $request->input('symbol'),
            $request->input('side')
        );

        return OrderResource::collection($orders);
    }

    /**
     * Place a new order.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            if ($request->input('side') === 'buy') {
                $order = $this->orderService->placeBuyOrder(
                    $user,
                    $request->input('symbol'),
                    (float) $request->input('price'),
                    (float) $request->input('amount')
                );
            } else {
                $order = $this->orderService->placeSellOrder(
                    $user,
                    $request->input('symbol'),
                    (float) $request->input('price'),
                    (float) $request->input('amount')
                );
            }

            return response()->json([
                'data' => new OrderResource($order),
                'message' => 'Order placed successfully',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Cancel an order.
     */
    public function cancel(Request $request, Order $order): JsonResponse
    {
        try {
            $user = $request->user();

            // Check authorization
            if ($order->user_id !== $user->id) {
                return response()->json([
                    'message' => 'Unauthorized to cancel this order',
                ], 403);
            }

            $cancelledOrder = $this->orderService->cancelOrder($order, $user);

            return response()->json([
                'data' => new OrderResource($cancelledOrder),
                'message' => 'Order cancelled successfully',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }
}
