<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import MagicLinkController from '@/actions/App/Http/Controllers/Auth/MagicLinkController';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { login } from '@/routes';

defineOptions({
    layout: {
        title: t('Check your email'),
        description: t(
            'Open the link in the email, on any device, or type its code here',
        ),
    },
});

defineProps<{
    email: string;
    status?: string;
}>();
</script>

<template>
    <Head :title="t('Check your email')" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <Form
        v-bind="MagicLinkController.storeCode.form()"
        :reset-on-error="['code']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="code">{{
                    t('6-digit code sent to :email', { email })
                }}</Label>
                <Input
                    id="code"
                    name="code"
                    required
                    v-focus
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    pattern="[0-9]{6}"
                    maxlength="6"
                    placeholder="123456"
                    class="text-center text-lg tracking-[0.5em]"
                />
                <InputError :message="errors.code" />
            </div>

            <Button
                type="submit"
                class="w-full"
                :disabled="processing"
                data-test="code-button"
            >
                <Spinner v-if="processing" />
                {{ t('Log in') }}
            </Button>
        </div>
    </Form>

    <div class="mt-6 text-center text-sm text-muted-foreground">
        <TextLink :href="login()">{{ t('Use a different email') }}</TextLink>
    </div>
</template>
