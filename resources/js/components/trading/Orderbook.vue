<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useOrders } from '@/composables/useOrders';

const props = defineProps<{
    symbol: string;
}>();

const { orders, loading, fetchOrderbook, buyOrders, sellOrders } = useOrders();

// Aggregate orders by price level
interface PriceLevel {
    price: string;
    amount: number;
    total: number;
    count: number;
}

const aggregatedBids = computed<PriceLevel[]>(() => {
    const levels = new Map<string, PriceLevel>();
    
    for (const order of buyOrders.value) {
        const existing = levels.get(order.price);
        if (existing) {
            existing.amount += parseFloat(order.amount);
            existing.total += parseFloat(order.total);
            existing.count++;
        } else {
            levels.set(order.price, {
                price: order.price,
                amount: parseFloat(order.amount),
                total: parseFloat(order.total),
                count: 1,
            });
        }
    }
    
    return Array.from(levels.values()).slice(0, 10);
});

const aggregatedAsks = computed<PriceLevel[]>(() => {
    const levels = new Map<string, PriceLevel>();
    
    for (const order of sellOrders.value) {
        const existing = levels.get(order.price);
        if (existing) {
            existing.amount += parseFloat(order.amount);
            existing.total += parseFloat(order.total);
            existing.count++;
        } else {
            levels.set(order.price, {
                price: order.price,
                amount: parseFloat(order.amount),
                total: parseFloat(order.total),
                count: 1,
            });
        }
    }
    
    return Array.from(levels.values()).slice(0, 10);
});

const spread = computed(() => {
    if (aggregatedBids.value.length === 0 || aggregatedAsks.value.length === 0) {
        return null;
    }
    
    const highestBid = parseFloat(aggregatedBids.value[0]?.price || '0');
    const lowestAsk = parseFloat(aggregatedAsks.value[0]?.price || '0');
    
    if (highestBid === 0 || lowestAsk === 0) return null;
    
    const spreadValue = lowestAsk - highestBid;
    const spreadPercent = (spreadValue / lowestAsk) * 100;
    
    return {
        value: spreadValue,
        percent: spreadPercent,
    };
});

function formatPrice(price: string | number): string {
    const value = typeof price === 'string' ? parseFloat(price) : price;
    return '$' + value.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function formatAmount(amount: number): string {
    return amount.toLocaleString('en-US', {
        minimumFractionDigits: 4,
        maximumFractionDigits: 8,
    });
}

// Fetch orderbook on mount and when symbol changes
onMounted(() => {
    fetchOrderbook(props.symbol);
});

watch(() => props.symbol, (newSymbol) => {
    fetchOrderbook(newSymbol);
});

// Expose refresh method
defineExpose({
    refresh: () => fetchOrderbook(props.symbol),
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Orderbook - {{ symbol }}/USD</CardTitle>
        </CardHeader>
        <CardContent>
            <!-- Loading State -->
            <div v-if="loading" class="space-y-2">
                <Skeleton class="h-40 w-full" />
            </div>

            <div v-else class="grid grid-cols-2 gap-4">
                <!-- Bids (Buy Orders) -->
                <div>
                    <div class="text-sm font-medium text-green-600 dark:text-green-400 mb-2">
                        Bids (Buy)
                    </div>
                    <div class="space-y-1">
                        <div class="grid grid-cols-2 text-xs text-muted-foreground border-b pb-1">
                            <span>Price</span>
                            <span class="text-right">Amount</span>
                        </div>
                        <div 
                            v-for="level in aggregatedBids" 
                            :key="level.price"
                            class="grid grid-cols-2 text-sm py-1 hover:bg-green-500/10"
                        >
                            <span class="text-green-600 dark:text-green-400 font-mono">
                                {{ formatPrice(level.price) }}
                            </span>
                            <span class="text-right font-mono">
                                {{ formatAmount(level.amount) }}
                            </span>
                        </div>
                        <div v-if="aggregatedBids.length === 0" class="text-sm text-muted-foreground text-center py-4">
                            No bids
                        </div>
                    </div>
                </div>

                <!-- Asks (Sell Orders) -->
                <div>
                    <div class="text-sm font-medium text-red-600 dark:text-red-400 mb-2">
                        Asks (Sell)
                    </div>
                    <div class="space-y-1">
                        <div class="grid grid-cols-2 text-xs text-muted-foreground border-b pb-1">
                            <span>Price</span>
                            <span class="text-right">Amount</span>
                        </div>
                        <div 
                            v-for="level in aggregatedAsks" 
                            :key="level.price"
                            class="grid grid-cols-2 text-sm py-1 hover:bg-red-500/10"
                        >
                            <span class="text-red-600 dark:text-red-400 font-mono">
                                {{ formatPrice(level.price) }}
                            </span>
                            <span class="text-right font-mono">
                                {{ formatAmount(level.amount) }}
                            </span>
                        </div>
                        <div v-if="aggregatedAsks.length === 0" class="text-sm text-muted-foreground text-center py-4">
                            No asks
                        </div>
                    </div>
                </div>
            </div>

            <!-- Spread -->
            <div v-if="spread" class="mt-4 pt-4 border-t text-center">
                <span class="text-sm text-muted-foreground">Spread: </span>
                <span class="font-medium">{{ formatPrice(spread.value) }}</span>
                <span class="text-muted-foreground text-sm"> ({{ spread.percent.toFixed(2) }}%)</span>
            </div>
        </CardContent>
    </Card>
</template>
