<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { 
    WalletOverview, 
    LimitOrderForm, 
    OrdersTable, 
    Orderbook 
} from '@/components/trading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { useProfile } from '@/composables/useProfile';
import { useOrders } from '@/composables/useOrders';
import { useEchoListeners } from '@/composables/useEchoListeners';
import type { OrderMatchedPayload } from '@/types/trading';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Trading', href: '/trading' },
];

const page = usePage();
const user = page.props.auth?.user as { id: number; name: string } | undefined;

// Profile composable
const { 
    profile, 
    balance, 
    assets, 
    fetchProfile, 
} = useProfile();

// Orders composable for user's orders
const userOrders = ref<any[]>([]);
const loadingOrders = ref(false);
const orderbookRef = ref<any>(null);
const walletRef = ref<any>(null);

const { cancelOrder } = useOrders();

// Active symbol
const activeSymbol = ref('BTC');

// Toast notification state
const notification = ref<{ type: 'success' | 'info'; message: string; trade?: any } | null>(null);

// Real-time Echo listeners with callbacks
const { isConnected, lastEvent, recentTrades } = useEchoListeners(user?.id, {
    onOrderMatched: async (payload: OrderMatchedPayload) => {
        // Show notification
        const role = payload.buyer_id === user?.id ? 'bought' : 'sold';
        notification.value = {
            type: 'success',
            message: `Order matched! You ${role} ${payload.trade.amount} ${payload.trade.symbol} at $${parseFloat(payload.trade.price).toLocaleString()}`,
            trade: payload.trade,
        };
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            notification.value = null;
        }, 5000);
        
        // Auto-refresh data
        await refreshAllData();
    },
});

async function fetchUserOrders() {
    loadingOrders.value = true;
    try {
        const response = await fetch('/api/profile', {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
        });
        if (response.ok) {
            const data = await response.json();
            userOrders.value = data.data.open_orders || [];
        }
    } catch (e) {
        console.error('Failed to fetch orders:', e);
    } finally {
        loadingOrders.value = false;
    }
}

async function refreshAllData() {
    await Promise.all([
        fetchProfile(),
        fetchUserOrders(),
    ]);
    // Refresh orderbook and wallet
    orderbookRef.value?.refresh?.();
    walletRef.value?.refresh?.();
}

async function handleCancelOrder(orderId: number) {
    const success = await cancelOrder(orderId);
    if (success) {
        notification.value = {
            type: 'info',
            message: 'Order cancelled successfully',
        };
        setTimeout(() => notification.value = null, 3000);
        await refreshAllData();
    }
}

async function handleOrderPlaced() {
    notification.value = {
        type: 'info',
        message: 'Order placed successfully!',
    };
    setTimeout(() => notification.value = null, 3000);
    await refreshAllData();
}

onMounted(async () => {
    await refreshAllData();
});
</script>

<template>
    <Head title="Trading" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <!-- Real-time Notification Toast -->
            <Transition
                enter-active-class="transition ease-out duration-300"
                enter-from-class="opacity-0 translate-y-[-10px]"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <Alert 
                    v-if="notification" 
                    :class="notification.type === 'success' 
                        ? 'border-green-500 bg-green-50 dark:bg-green-950' 
                        : 'border-blue-500 bg-blue-50 dark:bg-blue-950'"
                >
                    <AlertTitle :class="notification.type === 'success' ? 'text-green-700 dark:text-green-300' : 'text-blue-700 dark:text-blue-300'">
                        {{ notification.type === 'success' ? '🎉 Trade Executed!' : 'ℹ️ Info' }}
                    </AlertTitle>
                    <AlertDescription :class="notification.type === 'success' ? 'text-green-600 dark:text-green-400' : 'text-blue-600 dark:text-blue-400'">
                        {{ notification.message }}
                    </AlertDescription>
                </Alert>
            </Transition>

            <!-- Connection Status -->
            <div v-if="isConnected" class="text-xs text-green-600 dark:text-green-400 flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                Real-time updates active
            </div>

            <!-- Top Section: Wallet + Order Form + Orderbook -->
            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Wallet Overview -->
                <WalletOverview ref="walletRef" :user-id="user?.id" />

                <!-- Limit Order Form -->
                <LimitOrderForm 
                    :balance="balance" 
                    :assets="assets"
                    @order-placed="handleOrderPlaced"
                    @symbol-change="activeSymbol = $event"
                />

                <!-- Orderbook -->
                <Orderbook ref="orderbookRef" :symbol="activeSymbol" />
            </div>

            <!-- Recent Trades (from real-time events) -->
            <div v-if="recentTrades.length > 0" class="text-sm">
                <h3 class="font-medium mb-2">Recent Trades</h3>
                <div class="flex flex-wrap gap-2">
                    <div 
                        v-for="trade in recentTrades.slice(0, 5)" 
                        :key="trade.id"
                        class="px-3 py-1 rounded-full bg-muted text-xs"
                    >
                        {{ trade.symbol }} @ ${{ parseFloat(trade.price).toLocaleString() }}
                    </div>
                </div>
            </div>

            <!-- Bottom Section: Orders Table -->
            <OrdersTable 
                :orders="userOrders" 
                :loading="loadingOrders"
                @cancel="handleCancelOrder"
                @refresh="fetchUserOrders"
            />
        </div>
    </AppLayout>
</template>

