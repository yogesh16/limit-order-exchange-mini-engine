<script setup lang="ts">
import { onMounted, onUnmounted, computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useProfile } from '@/composables/useProfile';

const props = defineProps<{
    userId?: number;
}>();

const { 
    loading, 
    balance, 
    assets, 
    fetchProfile, 
    subscribeToOrderEvents, 
    unsubscribeFromOrderEvents 
} = useProfile();

const formattedBalance = computed(() => {
    const value = parseFloat(balance.value);
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(value);
});

function formatAssetAmount(amount: string): string {
    const value = parseFloat(amount);
    return value.toLocaleString('en-US', {
        minimumFractionDigits: 4,
        maximumFractionDigits: 8,
    });
}

onMounted(async () => {
    await fetchProfile();
    
    if (props.userId) {
        subscribeToOrderEvents(props.userId);
    }
});

onUnmounted(() => {
    if (props.userId) {
        unsubscribeFromOrderEvents(props.userId);
    }
});

// Expose refresh method for parent components
defineExpose({
    refresh: fetchProfile,
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Wallet</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
            <!-- USD Balance -->
            <div class="p-4 rounded-lg bg-gradient-to-r from-green-500/10 to-emerald-500/10 border border-green-500/20">
                <div class="text-sm text-muted-foreground mb-1">USD Balance</div>
                <Skeleton v-if="loading" class="h-8 w-32" />
                <div v-else class="text-2xl font-bold text-green-600 dark:text-green-400">
                    {{ formattedBalance }}
                </div>
            </div>

            <!-- Assets -->
            <div class="space-y-2">
                <div class="text-sm font-medium text-muted-foreground">Assets</div>
                
                <Skeleton v-if="loading" class="h-16 w-full" />
                
                <div v-else-if="assets.length === 0" class="text-sm text-muted-foreground text-center py-4">
                    No assets yet
                </div>
                
                <div v-else class="space-y-2">
                    <div 
                        v-for="asset in assets" 
                        :key="asset.id"
                        class="flex items-center justify-between p-3 rounded-lg bg-muted/50"
                    >
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-xs font-bold">
                                {{ asset.symbol.slice(0, 2) }}
                            </div>
                            <div>
                                <div class="font-medium">{{ asset.symbol }}</div>
                                <div class="text-xs text-muted-foreground">
                                    Locked: {{ formatAssetAmount(asset.locked_amount) }}
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-medium">{{ formatAssetAmount(asset.amount) }}</div>
                            <div class="text-xs text-muted-foreground">
                                Total: {{ formatAssetAmount(asset.total) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
