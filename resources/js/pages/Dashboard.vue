<script setup lang="ts">
import { ref, onMounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage, Link } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useProfile } from '@/composables/useProfile';
import { useEchoListeners } from '@/composables/useEchoListeners';
import type { OrderMatchedPayload } from '@/types/trading';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const page = usePage();
const user = page.props.auth?.user as { id: number; name: string } | undefined;

// Profile composable
const { 
    balance, 
    assets, 
    fetchProfile, 
} = useProfile();

// Orders
const userOrders = ref<any[]>([]);
const loadingOrders = ref(false);
const orderbookRef = ref<any>(null);

// Notification state
const notification = ref<{ type: 'success' | 'info'; message: string } | null>(null);

// Real-time listeners
const { isConnected, recentTrades } = useEchoListeners(user?.id, {
    onOrderMatched: async (payload: OrderMatchedPayload) => {
        const role = payload.buyer_id === user?.id ? 'bought' : 'sold';
        notification.value = {
            type: 'success',
            message: `🎉 Trade executed! You ${role} ${payload.trade.amount} ${payload.trade.symbol} at $${parseFloat(payload.trade.price).toLocaleString()}`,
        };
        setTimeout(() => notification.value = null, 5000);
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
    await Promise.all([fetchProfile(), fetchUserOrders()]);
    orderbookRef.value?.refresh?.();
}


onMounted(async () => {
    await refreshAllData();
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
            <!-- Notification Toast -->
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
                    <AlertDescription :class="notification.type === 'success' ? 'text-green-600 dark:text-green-400' : 'text-blue-600 dark:text-blue-400'">
                        {{ notification.message }}
                    </AlertDescription>
                </Alert>
            </Transition>

            <!-- Connection Status -->
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Welcome, {{ user?.name || 'Trader' }}!</h1>
                <div v-if="isConnected" class="text-xs text-green-600 dark:text-green-400 flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                    Live updates active
                </div>
            </div>

            <!-- Top Section: Quick Stats -->
            <div class="grid gap-4 md:grid-cols-4">
                <!-- USD Balance Card -->
                <Card class="bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-950 dark:to-emerald-950 border-green-200 dark:border-green-800">
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">USD Balance</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                            ${{ parseFloat(balance).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                        </div>
                    </CardContent>
                </Card>

                <!-- Assets Count -->
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">Assets Held</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ assets.length }}</div>
                        <div class="text-xs text-muted-foreground">Different symbols</div>
                    </CardContent>
                </Card>

                <!-- Open Orders -->
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">Open Orders</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ userOrders.length }}</div>
                        <div class="text-xs text-muted-foreground">Pending execution</div>
                    </CardContent>
                </Card>

                <!-- Recent Trades -->
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">Recent Trades</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ recentTrades.length }}</div>
                        <div class="text-xs text-muted-foreground">This session</div>
                    </CardContent>
                </Card>
            </div>

            <!-- Quick Link to Trading Page -->
            <div class="text-center">
                <Link href="/trading">
                    <Button variant="outline" size="lg">
                        Open Full Trading View →
                    </Button>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>

