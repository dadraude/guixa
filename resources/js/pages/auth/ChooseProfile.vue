<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Home, ShieldCheck } from '@lucide/vue';
import ActiveProfileController from '@/actions/App/Http/Controllers/Auth/ActiveProfileController';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import type { ActingAs } from '@/types';

defineOptions({
    layout: {
        title: t('Choose how you want to act'),
        description: t(
            'You are both a member and an administrator. You can switch whenever you want without logging in again.',
        ),
    },
});

defineProps<{
    profiles: ActingAs[];
}>();

const labels: Record<ActingAs, string> = {
    member: t('Act as member'),
    administrator: t('Act as administrator'),
};

const icons = { member: Home, administrator: ShieldCheck };
</script>

<template>
    <Head :title="t('Choose how you want to act')" />

    <div class="flex flex-col gap-4">
        <Form
            v-for="profile in profiles"
            :key="profile"
            v-bind="ActiveProfileController.update.form()"
            v-slot="{ processing }"
        >
            <input type="hidden" name="profile" :value="profile" />
            <Button
                type="submit"
                class="w-full"
                :variant="profile === 'member' ? 'default' : 'secondary'"
                :disabled="processing"
                :data-test="`choose-${profile}-button`"
            >
                <Spinner v-if="processing" />
                <component :is="icons[profile]" v-else />
                {{ labels[profile] }}
            </Button>
        </Form>
    </div>
</template>
