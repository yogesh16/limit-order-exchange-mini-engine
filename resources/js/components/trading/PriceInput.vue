<script setup lang="ts">
import { ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    modelValue: number | string;
    label?: string;
    symbol?: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: number): void;
}>();

// Use local string state for smooth typing
const localValue = ref(props.modelValue?.toString() || '');

// Sync from parent when modelValue changes externally
watch(() => props.modelValue, (newVal) => {
    const newStr = newVal?.toString() || '';
    if (parseFloat(localValue.value) !== parseFloat(newStr)) {
        localValue.value = newStr;
    }
});

function handleChange() {
    const value = parseFloat(localValue.value) || 0;
    emit('update:modelValue', value);
}
</script>

<template>
    <div class="space-y-2">
        <Label v-if="label">{{ label }}</Label>
        <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">$</span>
            <Input
                type="number"
                step="0.01"
                min="0"
                v-model="localValue"
                class="pl-7"
                placeholder="0.00"
                @change="handleChange"
                @blur="handleChange"
            />
        </div>
    </div>
</template>
