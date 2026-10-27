<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';

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
        <span>{{ trans(label) }}</span>
    </span>
</template>
