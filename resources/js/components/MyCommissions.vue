<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { t } from '@/lib/i18n';

type OwnCommission = {
    id: string;
    name: string;
    roles: { value: string; label: string }[];
};

defineProps<{
    commissions: OwnCommission[];
}>();
</script>

<template>
    <section class="flex flex-col gap-2" data-test="my-commissions">
        <Heading variant="small" :title="t('Your commissions')" />
        <p
            v-if="commissions.length === 0"
            class="text-sm text-muted-foreground"
        >
            {{ t('You are not in any commission.') }}
        </p>
        <ul v-else class="flex flex-col gap-1 text-sm">
            <li v-for="commission in commissions" :key="commission.id">
                <span class="font-medium">{{ commission.name }}</span>
                <span class="text-muted-foreground">
                    ·
                    {{
                        commission.roles.length === 0
                            ? t('No roles')
                            : commission.roles
                                  .map((role) => role.label)
                                  .join(', ')
                    }}
                </span>
            </li>
        </ul>
    </section>
</template>
