<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { OrderStatusBadge } from '@/components/trading';
import type { Order } from '@/types/trading';

const props = defineProps<{
    orders?: Order[];
    loading?: boolean;
}>();

const emit = defineEmits<{
    (e: 'cancel', orderId: number): void;
    (e: 'refresh'): void;
}>();

const cancelling = ref<number | null>(null);

function formatPrice(price: string): string {
    return '$' + parseFloat(price).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function formatAmount(amount: string, symbol: string): string {
    return parseFloat(amount).toLocaleString('en-US', {
        minimumFractionDigits: 4,
        maximumFractionDigits: 8,
    }) + ' ' + symbol;
}

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleString();
}

function getSideLabel(side: number | string): string {
    // Handle both numeric (legacy) and string values
    if (side === 1 || side === 'buy') return 'Buy';
    if (side === 2 || side === 'sell') return 'Sell';
    return String(side);
}

function getSideClass(side: number | string): string {
    if (side === 1 || side === 'buy') return 'text-green-600 dark:text-green-400';
    if (side === 2 || side === 'sell') return 'text-red-600 dark:text-red-400';
    return '';
}

async function handleCancel(orderId: number) {
    cancelling.value = orderId;
    emit('cancel', orderId);
    // Reset after a brief delay (parent should handle actual cancellation)
    setTimeout(() => {
        cancelling.value = null;
    }, 2000);
}
</script>

<template>
    <Card>
        <CardHeader class="flex flex-row items-center justify-between">
            <CardTitle>My Orders</CardTitle>
            <Button variant="ghost" size="sm" @click="emit('refresh')">
                Refresh
            </Button>
        </CardHeader>
        <CardContent>
            <!-- Loading State -->
            <div v-if="loading" class="space-y-2">
                <Skeleton v-for="i in 3" :key="i" class="h-12 w-full" />
            </div>

            <!-- Empty State -->
            <div v-else-if="!orders || orders.length === 0" class="text-center py-8 text-muted-foreground">
                No orders yet
            </div>

            <!-- Orders Table -->
            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 px-2">Symbol</th>
                            <th class="text-left py-2 px-2">Side</th>
                            <th class="text-right py-2 px-2">Price</th>
                            <th class="text-right py-2 px-2">Amount</th>
                            <th class="text-center py-2 px-2">Status</th>
                            <th class="text-right py-2 px-2">Date</th>
                            <th class="text-center py-2 px-2">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr 
                            v-for="order in orders" 
                            :key="order.id"
                            class="border-b hover:bg-muted/50"
                        >
                            <td class="py-2 px-2 font-medium">{{ order.symbol }}/USD</td>
                            <td class="py-2 px-2" :class="getSideClass(order.side)">
                                {{ getSideLabel(order.side) }}
                            </td>
                            <td class="py-2 px-2 text-right font-mono">
                                {{ formatPrice(order.price) }}
                            </td>
                            <td class="py-2 px-2 text-right font-mono">
                                {{ formatAmount(order.amount, order.symbol) }}
                            </td>
                            <td class="py-2 px-2 text-center">
                                <OrderStatusBadge :status="order.status" />
                            </td>
                            <td class="py-2 px-2 text-right text-muted-foreground text-xs">
                                {{ formatDate(order.created_at) }}
                            </td>
                            <td class="py-2 px-2 text-center">
                                <Button
                                    v-if="order.status === 1"
                                    variant="destructive"
                                    size="sm"
                                    :disabled="cancelling === order.id"
                                    @click="handleCancel(order.id)"
                                >
                                    {{ cancelling === order.id ? '...' : 'Cancel' }}
                                </Button>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </CardContent>
    </Card>
</template>
