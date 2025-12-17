<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    modelValue: string;
    symbols?: string[];
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const availableSymbols = computed(() => props.symbols ?? ['BTC', 'ETH']);

function selectSymbol(symbol: string) {
    emit('update:modelValue', symbol);
}
</script>

<template>
    <div class="flex gap-2">
        <Button
            v-for="symbol in availableSymbols"
            :key="symbol"
            :variant="modelValue === symbol ? 'default' : 'outline'"
            size="sm"
            @click="selectSymbol(symbol)"
        >
            {{ symbol }}/USD
        </Button>
    </div>
</template>
