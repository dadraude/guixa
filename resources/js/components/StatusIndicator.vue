<script setup lang="ts">
withDefaults(
    defineProps<{
        label: string;
        tone?: 'success' | 'warning' | 'danger' | 'neutral';
    }>(),
    { tone: 'neutral' },
);
</script>

<template>
    <span class="material-status" :data-tone="tone">
        <span class="material-status-dot" aria-hidden="true" />
        <span>{{ label }}</span>
    </span>
</template>
