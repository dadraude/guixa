<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import StatusIndicator from '@/components/StatusIndicator.vue';

const props = defineProps<{
    name: string;
    email: string;
    status: Guixa.Membership.Domain.AccountStatus;
}>();

// Text, not colour alone, tells the manager which accounts cannot sign in yet or any more (R9).
const marker = computed(() => {
    const markers: Partial<
        Record<
            Guixa.Membership.Domain.AccountStatus,
            { label: string; tone: 'warning' | 'neutral' }
        >
    > = {
        invitation_pending: {
            label: trans('Invitation pending'),
            tone: 'warning',
        },
        invitation_expired: {
            label: trans('Invitation expired'),
            tone: 'warning',
        },
        deactivated: { label: trans('Deactivated'), tone: 'neutral' },
    };

    return markers[props.status];
});
</script>

<template>
    <li class="pilot-list-row">
        <div class="pilot-list-row-body">
            <p class="font-medium break-words">{{ name }}</p>
            <p class="text-sm break-all text-muted-foreground">{{ email }}</p>
        </div>
        <StatusIndicator
            v-if="marker"
            :label="marker.label"
            :tone="marker.tone"
        />
        <slot />
    </li>
</template>
