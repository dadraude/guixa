<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { t } from '@/lib/i18n';
import { index } from '@/routes/directory';

type MemberRow = {
    id: string;
    name: string;
    email: string;
    phone: string | null;
};

type HouseholdRow = {
    id: string;
    number: number;
    name: string;
    is_active: boolean;
    members: MemberRow[];
};

defineProps<{
    isFull: boolean;
    households: HouseholdRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('Directory'), href: index() }],
    },
});
</script>

<template>
    <Head :title="t('Directory')" />

    <div class="flex h-full flex-1 flex-col gap-8 p-4">
        <Heading
            :title="t('Directory')"
            :description="
                isFull
                    ? t('Households and members with their contact details.')
                    : t(
                          'You only see your own household. Members of a commission see everyone.',
                      )
            "
        />

        <p v-if="households.length === 0" class="text-muted-foreground">
            {{ t('No households yet.') }}
        </p>

        <ul v-else class="divide-y rounded-lg border" data-test="directory">
            <li
                v-for="household in households"
                :key="household.id"
                class="flex flex-col gap-3 p-4"
                data-test="directory-household"
            >
                <div class="flex flex-wrap items-center gap-3">
                    <span
                        class="font-mono text-sm text-muted-foreground"
                        :title="t('Household number')"
                        >#{{ household.number }}</span
                    >
                    <span class="font-medium">{{ household.name }}</span>
                    <Badge v-if="!household.is_active" variant="outline">{{
                        t('Inactive')
                    }}</Badge>
                </div>

                <p
                    v-if="household.members.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    {{ t('No members yet.') }}
                </p>

                <ul v-else class="flex flex-col gap-2 text-sm">
                    <li
                        v-for="member in household.members"
                        :key="member.id"
                        class="flex flex-wrap gap-x-4 gap-y-1"
                    >
                        <span class="font-medium">{{ member.name }}</span>
                        <a
                            :href="`mailto:${member.email}`"
                            class="text-muted-foreground underline"
                            >{{ member.email }}</a
                        >
                        <a
                            v-if="member.phone"
                            :href="`tel:${member.phone}`"
                            class="text-muted-foreground underline"
                            >{{ member.phone }}</a
                        >
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</template>
