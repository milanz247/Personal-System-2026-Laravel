<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        value: number;
        duration?: number;
        formatter?: (val: number) => string;
    }>(),
    {
        duration: 1500,
        formatter: (val: number) => String(val),
    }
);

const displayValue = ref(props.value);
let animationFrameId: number | null = null;

const animate = (from: number, to: number) => {
    if (animationFrameId) {
        cancelAnimationFrame(animationFrameId);
    }
    
    let startTimestamp: number | null = null;
    const step = (timestamp: number) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / props.duration, 1);
        const easeProgress = 1 - Math.pow(1 - progress, 3); // easeOutCubic
        displayValue.value = from + easeProgress * (to - from);
        
        if (progress < 1) {
            animationFrameId = window.requestAnimationFrame(step);
        } else {
            displayValue.value = to;
        }
    };
    animationFrameId = window.requestAnimationFrame(step);
};

onMounted(() => {
    if (typeof window !== 'undefined') {
        animate(0, props.value);
    }
});

watch(
    () => props.value,
    (newVal, oldVal) => {
        if (typeof window !== 'undefined') {
            animate(oldVal || 0, newVal);
        } else {
            displayValue.value = newVal;
        }
    }
);
</script>

<template>
    <span>{{ formatter(displayValue) }}</span>
</template>
