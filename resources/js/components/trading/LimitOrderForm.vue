<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { SymbolSelector } from '@/components/trading';
import { OrderSideToggle } from '@/components/trading';
import { PriceInput } from '@/components/trading';
import { AmountInput } from '@/components/trading';
import { BalanceDisplay } from '@/components/trading';
import { useOrders } from '@/composables/useOrders';
import type { CreateOrderRequest } from '@/types/trading';

const props = defineProps<{
    balance: string;
    assets?: Array<{ symbol: string; amount: string; locked_amount: string }>;
}>();

const emit = defineEmits<{
    (e: 'orderPlaced'): void;
    (e: 'symbolChange', symbol: string): void;
}>();

const { placeOrder, submitting, error } = useOrders();

// Form state
const symbol = ref<string>('BTC');
const side = ref<'buy' | 'sell'>('buy');
const price = ref<number>(0);
const amount = ref<number>(0);
const success = ref<string | null>(null);

// Computed values
const COMMISSION_RATE = 0.015; // 1.5%

const total = computed(() => {
    return price.value * amount.value;
});

const commission = computed(() => {
    return total.value * COMMISSION_RATE;
});

// For buy orders: commission is taken from asset received
const assetCommission = computed(() => {
    return amount.value * COMMISSION_RATE;
});

const assetReceived = computed(() => {
    return amount.value - assetCommission.value;
});

const finalTotal = computed(() => {
    if (side.value === 'buy') {
        return total.value; // Commission taken from asset received
    }
    return total.value - commission.value; // Seller receives less
});

const availableBalance = computed(() => {
    if (side.value === 'buy') {
        return parseFloat(props.balance);
    }
    const asset = props.assets?.find(a => a.symbol === symbol.value);
    return asset ? parseFloat(asset.amount) : 0;
});

const canSubmit = computed(() => {
    if (price.value <= 0 || amount.value <= 0) return false;
    if (side.value === 'buy' && total.value > parseFloat(props.balance)) return false;
    if (side.value === 'sell' && amount.value > availableBalance.value) return false;
    return true;
});

// Handle form submission
async function handleSubmit() {
    success.value = null;
    
    const orderData: CreateOrderRequest = {
        symbol: symbol.value,
        side: side.value,
        price: price.value,
        amount: amount.value,
    };

    const order = await placeOrder(orderData);
    
    if (order) {
        success.value = `Order placed successfully! ID: ${order.id}`;
        // Reset form
        price.value = 0;
        amount.value = 0;
        emit('orderPlaced');
        
        // Clear success message after 5 seconds
        setTimeout(() => {
            success.value = null;
        }, 5000);
    }
}

// Clear messages when form changes and emit symbol change
watch([symbol, side], () => {
    success.value = null;
});

// Emit symbol change to parent for orderbook sync
watch(symbol, (newSymbol) => {
    emit('symbolChange', newSymbol);
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Place Order</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
            <!-- Symbol Selection -->
            <div class="space-y-2">
                <Label>Symbol</Label>
                <SymbolSelector v-model="symbol" />
            </div>

            <!-- Buy/Sell Toggle -->
            <div class="space-y-2">
                <Label>Side</Label>
                <OrderSideToggle v-model="side" />
            </div>

            <!-- Available Balance -->
            <BalanceDisplay 
                :balance="side === 'buy' ? balance : availableBalance.toString()" 
                :symbol="side === 'buy' ? 'USD' : symbol"
                label="Available"
            />

            <!-- Price Input -->
            <PriceInput 
                v-model="price" 
                label="Price (USD)" 
            />

            <!-- Amount Input -->
            <AmountInput 
                v-model="amount" 
                label="Amount" 
                :symbol="symbol"
            />

            <!-- Order Summary -->
            <div class="border-t pt-4 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-muted-foreground">Subtotal</span>
                    <span>${{ total.toFixed(2) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-muted-foreground">Commission (1.5%)</span>
                    <span v-if="side === 'buy'" class="text-orange-600 dark:text-orange-400">
                        -{{ assetCommission.toFixed(4) }} {{ symbol }}
                    </span>
                    <span v-else class="text-orange-600 dark:text-orange-400">
                        -${{ commission.toFixed(2) }}
                    </span>
                </div>
                <div class="border-t pt-2 mt-2">
                    <div class="flex justify-between font-medium">
                        <span>You Pay</span>
                        <span v-if="side === 'buy'">${{ total.toFixed(2) }}</span>
                        <span v-else>{{ amount.toFixed(4) }} {{ symbol }}</span>
                    </div>
                    <div class="flex justify-between font-medium text-green-600 dark:text-green-400">
                        <span>You Receive</span>
                        <span v-if="side === 'buy'">{{ assetReceived.toFixed(4) }} {{ symbol }}</span>
                        <span v-else>${{ finalTotal.toFixed(2) }}</span>
                    </div>
                </div>
            </div>

            <!-- Error Message -->
            <Alert v-if="error" variant="destructive">
                <AlertDescription>{{ error }}</AlertDescription>
            </Alert>

            <!-- Success Message -->
            <Alert v-if="success" class="border-green-500 bg-green-50 dark:bg-green-950">
                <AlertDescription class="text-green-700 dark:text-green-300">
                    {{ success }}
                </AlertDescription>
            </Alert>

            <!-- Submit Button -->
            <Button 
                class="w-full" 
                :class="side === 'buy' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'"
                :disabled="!canSubmit || submitting"
                @click="handleSubmit"
            >
                <span v-if="submitting">Processing...</span>
                <span v-else>{{ side === 'buy' ? 'Buy' : 'Sell' }} {{ symbol }}</span>
            </Button>
        </CardContent>
    </Card>
</template>
