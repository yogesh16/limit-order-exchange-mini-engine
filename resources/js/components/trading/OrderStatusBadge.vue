<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { OrderStatus, OrderStatusLabel } from '@/types/trading';

const props = defineProps<{
    status: OrderStatus | OrderStatusLabel;
}>();

const statusConfig = computed(() => {
    // Handle both numeric and string status (lowercase from backend API)
    const statusMap: Record<string | number, { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
        1: { label: 'Open', variant: 'outline' },
        'open': { label: 'Open', variant: 'outline' },
        'Open': { label: 'Open', variant: 'outline' },
        2: { label: 'Filled', variant: 'default' },
        'filled': { label: 'Filled', variant: 'default' },
        'Filled': { label: 'Filled', variant: 'default' },
        3: { label: 'Cancelled', variant: 'secondary' },
        'cancelled': { label: 'Cancelled', variant: 'secondary' },
        'Cancelled': { label: 'Cancelled', variant: 'secondary' },
    };
    
    return statusMap[props.status] || { label: 'Unknown', variant: 'secondary' as const };
});
</script>

<template>
    <Badge :variant="statusConfig.variant">
        {{ statusConfig.label }}
    </Badge>
</template>
