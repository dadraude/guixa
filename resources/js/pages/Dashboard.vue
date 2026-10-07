<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import MyCommissions from '@/components/MyCommissions.vue';
import { t } from '@/lib/i18n';
import { dashboard } from '@/routes';

type Household = {
    number: number;
    name: string;
    members: { id: string; name: string }[];
};

defineProps<{
    household: Household | null;
    commissions: {
        id: string;
        name: string;
        roles: { value: string; label: string }[];
    }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: t('Dashboard'),
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="t('Dashboard')" />

    <div class="flex h-full flex-1 flex-col gap-8 p-4">
        <p v-if="household === null" class="text-muted-foreground">
            {{ t('You do not belong to any household.') }}
        </p>

        <template v-else>
            <Heading
                :title="`${t('Your household')}: #${household.number} ${household.name}`"
            />

            <div class="flex flex-col gap-4">
                <Heading variant="small" :title="t('Members')" />
                <ul class="divide-y rounded-lg border" data-test="members">
                    <li
                        v-for="member in household.members"
                        :key="member.id"
                        class="p-4"
                    >
                        {{ member.name }}
                    </li>
                </ul>
            </div>
        </template>

        <MyCommissions
            v-if="household !== null || commissions.length > 0"
            :commissions="commissions"
        />
    </div>
</template>
