<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import EmailChangeController from '@/actions/App/Http/Controllers/Settings/EmailChangeController';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';

defineOptions({
    layout: {
        title: t('Confirm your new email address'),
        description: t('Press the button to change your email address'),
    },
});

defineProps<{
    token: string;
}>();
</script>

<template>
    <Head :title="t('Confirm your new email address')" />

    <Form
        v-bind="EmailChangeController.confirm.form(token)"
        v-slot="{ processing }"
        class="flex flex-col gap-6"
    >
        <Button
            type="submit"
            class="w-full"
            v-focus
            :disabled="processing"
            data-test="confirm-email-change-button"
        >
            <Spinner v-if="processing" />
            {{ t('Confirm email address') }}
        </Button>
    </Form>
</template>
