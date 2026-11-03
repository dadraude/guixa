<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import CycleController from '@/actions/App/Http/Controllers/Manage/CycleController';
import BasketController from '@/actions/App/Http/Controllers/Finance/BasketController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { formatMoney } from '@/lib/locale';

defineProps<{
    cycleId: string;
    closed?: boolean;
    pickups: App.Http.PickupSummaryRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Cycles', href: CycleController.index() }],
    },
});

const statusLabels: Record<string, string> = {
    draft: trans('Draft'),
    validated: trans('Pending collection'),
    collected: trans('Collected'),
    nothing_to_collect: trans('Nothing to collect'),
};

const euros = (cents: number) => formatMoney((cents / 100).toFixed(2));
</script>

<template>
    <Head :title="trans('Pickups')" />

    <div class="pilot-page pilot-layout-page pilot-manager-page">
        <Heading
            :title="trans('Pickups')"
            :description="
                trans(
                    'Each household has a weekly basket, plus a late one for goods that arrive after it is validated: validate each to charge it, then mark it collected.',
                )
            "
        />

        <p
            v-if="closed"
            class="text-sm text-muted-foreground"
            data-test="cycle-closed"
        >
            {{
                trans(
                    'This cycle is closed. Baskets can still be collected and corrected.',
                )
            }}
        </p>

        <p
            v-if="pickups.length === 0"
            class="text-sm text-muted-foreground"
            data-test="no-pickups"
        >
            {{ trans('No household has a weekly basket in this cycle yet.') }}
        </p>

        <ul class="divide-y">
            <li
                v-for="pickup in pickups"
                :key="pickup.basketId"
                class="flex flex-wrap items-center justify-between gap-2 py-2"
                :data-test="`pickup-${pickup.basketId}`"
            >
                <Link
                    :href="
                        BasketController.show.url({ basket: pickup.basketId })
                    "
                    class="underline"
                >
                    {{ pickup.householdName }}
                </Link>
                <Badge
                    v-if="pickup.late"
                    variant="outline"
                    :data-test="`late-basket-${pickup.basketId}`"
                >
                    {{ trans('Late basket') }}
                </Badge>
                <Badge variant="secondary">
                    {{ statusLabels[pickup.status] ?? pickup.status }}
                </Badge>
                <span>{{ euros(pickup.totalCents) }}</span>
            </li>
        </ul>
    </div>
</template>
