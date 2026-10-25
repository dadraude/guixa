<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Button } from '@/components/ui/button';

defineProps<{ processing: boolean }>();
defineEmits<{ submit: [] }>();
</script>

<template>
    <form
        class="flex flex-col items-start gap-3"
        data-test="not-collected-form"
        @submit.prevent="$emit('submit')"
    >
        <p class="text-sm text-muted-foreground">
            {{
                trans(
                    'Charge the order quantities shown above. Stock and return lines will be removed.',
                )
            }}
        </p>
        <Button
            type="submit"
            variant="outline"
            class="min-h-11 whitespace-normal"
            :disabled="processing"
        >
            {{ trans('Mark as not collected') }}
        </Button>
    </form>
</template>
