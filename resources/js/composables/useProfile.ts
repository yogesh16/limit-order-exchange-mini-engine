import { ref, computed, onMounted } from 'vue';
import type { Profile, Asset, Order, OrderMatchedPayload } from '@/types/trading';
import echo from '@/echo';

/**
 * Composable for managing user profile state and real-time updates
 */
export function useProfile() {
    const profile = ref<Profile | null>(null);
    const loading = ref(false);
    const error = ref<string | null>(null);

    // Computed properties
    const balance = computed(() => profile.value?.balance ?? '0');
    const assets = computed(() => profile.value?.assets ?? []);
    const openOrders = computed(() => profile.value?.open_orders ?? []);

    /**
     * Fetch user profile from API
     */
    async function fetchProfile(): Promise<void> {
        loading.value = true;
        error.value = null;

        try {
            const response = await fetch('/api/profile', {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Failed to fetch profile');
            }

            const data = await response.json();
            profile.value = data.data;
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Unknown error';
        } finally {
            loading.value = false;
        }
    }

    /**
     * Get asset by symbol
     */
    function getAsset(symbol: string): Asset | undefined {
        return assets.value.find(a => a.symbol === symbol);
    }

    /**
     * Subscribe to real-time order matched events
     */
    function subscribeToOrderEvents(userId: number): void {
        echo.private(`user.${userId}`)
            .listen('.order.matched', (event: OrderMatchedPayload) => {
                console.log('Order matched:', event);
                // Refresh profile to get updated balances and assets
                fetchProfile();
            });
    }

    /**
     * Unsubscribe from order events
     */
    function unsubscribeFromOrderEvents(userId: number): void {
        echo.leave(`user.${userId}`);
    }

    return {
        // State
        profile,
        loading,
        error,

        // Computed
        balance,
        assets,
        openOrders,

        // Methods
        fetchProfile,
        getAsset,
        subscribeToOrderEvents,
        unsubscribeFromOrderEvents,
    };
}
