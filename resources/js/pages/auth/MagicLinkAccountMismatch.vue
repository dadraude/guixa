<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';
import { logout } from '@/routes';

defineOptions({ layout: { title: t('Log in') } });
defineProps<{ name: string }>();
</script>

<template>
    <Head :title="t('Log in')" />
    <p class="text-center text-sm">
        {{
            t('You are signed in as :name. Sign out to use this link.', {
                name,
            })
        }}
    </p>
    <Form v-bind="logout.form()" v-slot="{ processing }">
        <Button type="submit" class="w-full" :disabled="processing">
            {{ t('Log out') }}
        </Button>
    </Form>
</template>
