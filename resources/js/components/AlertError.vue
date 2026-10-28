<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { AlertCircle } from '@lucide/vue';
import { computed } from 'vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

type Props = {
    errors: string[];
    title?: string;
};

const props = defineProps<Props>();

const uniqueErrors = computed(() => Array.from(new Set(props.errors)));
const displayTitle = computed(
    () => props.title ?? trans('Something went wrong.'),
);
</script>

<template>
    <Alert variant="destructive">
        <AlertCircle class="size-4" />
        <AlertTitle>{{ displayTitle }}</AlertTitle>
        <AlertDescription>
            <ul class="list-inside list-disc text-sm">
                <li v-for="(error, index) in uniqueErrors" :key="index">
                    {{ error }}
                </li>
            </ul>
        </AlertDescription>
    </Alert>
</template>
