<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

defineProps<{
    processing: boolean;
    error?: string;
}>();

const emit = defineEmits<{
    complete: [topUp: string | null];
}>();

const topUp = ref('');

const submit = () => emit('complete', topUp.value.trim() || null);
</script>

<template>
    <form
        class="flex flex-wrap items-end gap-2"
        data-test="complete-form"
        @submit.prevent="submit"
    >
        <div class="flex flex-col gap-1">
            <label for="pickup-top-up" class="text-sm text-muted-foreground">
                {{ trans('Top-up (EUR)') }}
            </label>
            <Input
                id="pickup-top-up"
                v-model="topUp"
                inputmode="decimal"
                class="h-11 w-32"
                data-test="top-up"
            />
        </div>
        <Button
            type="submit"
            class="min-h-11"
            data-test="complete"
            :disabled="processing"
        >
            {{ trans('Complete pickup') }}
        </Button>
        <InputError :message="error" data-test="top-up-error" />
    </form>
</template>
