import { ref, onMounted, onUnmounted } from 'vue';
import echo from '@/echo';
import type { OrderMatchedPayload, Trade } from '@/types/trading';

export interface EchoCallbacks {
    onOrderMatched?: (payload: OrderMatchedPayload) => void;
    onOrderCreated?: (order: any) => void;
    onOrderCancelled?: (orderId: number) => void;
}

/**
 * Composable for managing Echo real-time event listeners
 * Provides reactive state updates and callback hooks for trading events
 */
export function useEchoListeners(userId: number | undefined, callbacks?: EchoCallbacks) {
    const isConnected = ref(false);
    const lastEvent = ref<OrderMatchedPayload | null>(null);
    const recentTrades = ref<Trade[]>([]);

    let channel: any = null;

    /**
     * Subscribe to user's private channel for order events
     */
    function subscribe() {
        if (!userId) {
            console.warn('useEchoListeners: No userId provided, skipping subscription');
            return;
        }

        channel = echo.private(`user.${userId}`);

        // Listen for order matched events
        channel.listen('.order.matched', (event: OrderMatchedPayload) => {
            console.log('[Echo] Order matched:', event);

            lastEvent.value = event;

            // Add to recent trades (keep last 10)
            recentTrades.value = [event.trade, ...recentTrades.value].slice(0, 10);

            // Call user callback if provided
            callbacks?.onOrderMatched?.(event);
        });

        // Listen for order created events (if implemented)
        channel.listen('.order.created', (event: any) => {
            console.log('[Echo] Order created:', event);
            callbacks?.onOrderCreated?.(event);
        });

        // Listen for order cancelled events (if implemented)
        channel.listen('.order.cancelled', (event: { order_id: number }) => {
            console.log('[Echo] Order cancelled:', event);
            callbacks?.onOrderCancelled?.(event.order_id);
        });

        isConnected.value = true;
        console.log(`[Echo] Subscribed to private-user.${userId}`);
    }

    /**
     * Unsubscribe from channel
     */
    function unsubscribe() {
        if (userId && channel) {
            echo.leave(`user.${userId}`);
            channel = null;
            isConnected.value = false;
            console.log(`[Echo] Unsubscribed from private-user.${userId}`);
        }
    }

    /**
     * Check if the current user is the buyer in a trade
     */
    function isBuyer(event: OrderMatchedPayload): boolean {
        return event.buyer_id === userId;
    }

    /**
     * Check if the current user is the seller in a trade
     */
    function isSeller(event: OrderMatchedPayload): boolean {
        return event.seller_id === userId;
    }

    /**
     * Get user's role in the trade
     */
    function getUserRole(event: OrderMatchedPayload): 'buyer' | 'seller' | null {
        if (event.buyer_id === userId) return 'buyer';
        if (event.seller_id === userId) return 'seller';
        return null;
    }

    // Auto-subscribe on mount if userId is provided
    onMounted(() => {
        if (userId) {
            subscribe();
        }
    });

    // Auto-unsubscribe on unmount
    onUnmounted(() => {
        unsubscribe();
    });

    return {
        // State
        isConnected,
        lastEvent,
        recentTrades,

        // Methods
        subscribe,
        unsubscribe,
        isBuyer,
        isSeller,
        getUserRole,
    };
}
