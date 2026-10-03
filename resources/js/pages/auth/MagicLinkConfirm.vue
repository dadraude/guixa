<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import MagicLinkController from '@/actions/App/Http/Controllers/Auth/MagicLinkController';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';

defineOptions({
    layout: {
        title: t('Log in'),
        description: t('Press the button to log in on this device'),
    },
});

defineProps<{
    token: string;
}>();
</script>

<template>
    <Head :title="t('Log in')" />

    <Form
        v-bind="MagicLinkController.consume.form(token)"
        v-slot="{ processing }"
        class="flex flex-col gap-6"
    >
        <Button
            type="submit"
            class="w-full"
            v-focus
            :disabled="processing"
            data-test="confirm-magic-link-button"
        >
            <Spinner v-if="processing" />
            {{ t('Log in') }}
        </Button>
    </Form>
</template>
