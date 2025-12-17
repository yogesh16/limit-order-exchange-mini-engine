import { ref, computed } from 'vue';
import type { Order, CreateOrderRequest, ApiResponse, ApiErrorResponse } from '@/types/trading';

/**
 * Get CSRF token from cookie
 */
function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * Composable for managing orders and orderbook
 */
export function useOrders() {
    const orders = ref<Order[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);
    const submitting = ref(false);

    // Computed properties
    // Side is string ('buy'/'sell'), Status is int (1=open, 2=filled, 3=cancelled)
    const buyOrders = computed(() =>
        orders.value
            .filter(o => o.side === 'buy' && o.status === 1)
            .sort((a, b) => parseFloat(b.price) - parseFloat(a.price))
    );

    const sellOrders = computed(() =>
        orders.value
            .filter(o => o.side === 'sell' && o.status === 1)
            .sort((a, b) => parseFloat(a.price) - parseFloat(b.price))
    );

    /**
     * Fetch orderbook for a symbol
     */
    async function fetchOrderbook(symbol: string, side?: 'buy' | 'sell'): Promise<void> {
        loading.value = true;
        error.value = null;

        try {
            const params = new URLSearchParams({ symbol });
            if (side) params.append('side', side);

            const response = await fetch(`/api/orders?${params}`, {
                headers: {
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to fetch orderbook');
            }

            const data = await response.json();
            orders.value = data.data;
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Unknown error';
        } finally {
            loading.value = false;
        }
    }

    /**
     * Place a new order
     */
    async function placeOrder(orderData: CreateOrderRequest): Promise<Order | null> {
        submitting.value = true;
        error.value = null;

        try {
            const response = await fetch('/api/orders', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify(orderData),
            });

            if (!response.ok) {
                const errorData: ApiErrorResponse = await response.json();
                throw new Error(errorData.message || 'Failed to place order');
            }

            const data: ApiResponse<Order> = await response.json();
            return data.data;
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Unknown error';
            return null;
        } finally {
            submitting.value = false;
        }
    }

    /**
     * Cancel an order
     */
    async function cancelOrder(orderId: number): Promise<boolean> {
        submitting.value = true;
        error.value = null;

        try {
            const response = await fetch(`/api/orders/${orderId}/cancel`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                const errorData: ApiErrorResponse = await response.json();
                throw new Error(errorData.message || 'Failed to cancel order');
            }

            // Remove from local state
            orders.value = orders.value.filter(o => o.id !== orderId);
            return true;
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Unknown error';
            return false;
        } finally {
            submitting.value = false;
        }
    }

    /**
     * Add order to local state (for real-time updates)
     */
    function addOrder(order: Order): void {
        orders.value.push(order);
    }

    /**
     * Update order in local state
     */
    function updateOrder(orderId: number, updates: Partial<Order>): void {
        const index = orders.value.findIndex(o => o.id === orderId);
        if (index !== -1) {
            orders.value[index] = { ...orders.value[index], ...updates };
        }
    }

    /**
     * Remove order from local state
     */
    function removeOrder(orderId: number): void {
        orders.value = orders.value.filter(o => o.id !== orderId);
    }

    return {
        // State
        orders,
        loading,
        error,
        submitting,

        // Computed
        buyOrders,
        sellOrders,

        // Methods
        fetchOrderbook,
        placeOrder,
        cancelOrder,
        addOrder,
        updateOrder,
        removeOrder,
    };
}
