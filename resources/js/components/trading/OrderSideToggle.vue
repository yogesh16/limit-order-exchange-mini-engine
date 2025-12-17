<script setup lang="ts">
import { Button } from '@/components/ui/button';

defineProps<{
    modelValue: 'buy' | 'sell';
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: 'buy' | 'sell'): void;
}>();

function selectSide(side: 'buy' | 'sell') {
    emit('update:modelValue', side);
}
</script>

<template>
    <div class="grid grid-cols-2 gap-2">
        <Button
            :variant="modelValue === 'buy' ? 'default' : 'outline'"
            :class="modelValue === 'buy' ? 'bg-green-600 hover:bg-green-700' : ''"
            @click="selectSide('buy')"
        >
            Buy
        </Button>
        <Button
            :variant="modelValue === 'sell' ? 'default' : 'outline'"
            :class="modelValue === 'sell' ? 'bg-red-600 hover:bg-red-700' : ''"
            @click="selectSide('sell')"
        >
            Sell
        </Button>
    </div>
</template>
