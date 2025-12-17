<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <svg width="120" height="120" viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg">
    <defs>
        <linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" stop-color="#2563EB"/>
        <stop offset="100%" stop-color="#1E3A8A"/>
        </linearGradient>
    </defs>

    <!-- Background -->
    <circle cx="60" cy="60" r="50" fill="url(#grad)" />

    <!-- Gear Center -->
    <circle cx="60" cy="60" r="18" fill="#FFFFFF"/>

    <!-- Gear Teeth -->
    <g fill="#FFFFFF">
        <rect x="58" y="8" width="4" height="16"/>
        <rect x="58" y="96" width="4" height="16"/>
        <rect x="8" y="58" width="16" height="4"/>
        <rect x="96" y="58" width="16" height="4"/>

        <rect x="20" y="20" width="4" height="14" transform="rotate(-45 22 27)"/>
        <rect x="96" y="20" width="4" height="14" transform="rotate(45 98 27)"/>
        <rect x="20" y="86" width="4" height="14" transform="rotate(45 22 93)"/>
        <rect x="96" y="86" width="4" height="14" transform="rotate(-45 98 93)"/>
    </g>

    <!-- Exchange Arrows -->
    <path d="M36 46h32l-6-6"
            stroke="#FFFFFF" stroke-width="3" fill="none"
            stroke-linecap="round" stroke-linejoin="round"/>

    <path d="M84 74H52l6 6"
            stroke="#FFFFFF" stroke-width="3" fill="none"
            stroke-linecap="round" stroke-linejoin="round"/>
    </svg>

</template>
