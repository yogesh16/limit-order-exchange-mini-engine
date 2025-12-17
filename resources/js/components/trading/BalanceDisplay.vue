<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    balance: string | number;
    symbol?: string;
    label?: string;
}>();

const formattedBalance = computed(() => {
    const value = typeof props.balance === 'string' ? parseFloat(props.balance) : props.balance;
    
    if (props.symbol === 'USD' || !props.symbol) {
        return '$' + value.toLocaleString('en-US', { 
            minimumFractionDigits: 2, 
            maximumFractionDigits: 2 
        });
    }
    
    return value.toLocaleString('en-US', { 
        minimumFractionDigits: 8, 
        maximumFractionDigits: 8 
    }) + ' ' + props.symbol;
});
</script>

<template>
    <div class="flex items-center justify-between text-sm">
        <span class="text-muted-foreground">{{ label ?? 'Available' }}</span>
        <span class="font-medium">{{ formattedBalance }}</span>
    </div>
</template>
