<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import BasketCollectionController from '@/actions/App/Http/Controllers/Member/BasketCollectionController';
import BasketController from '@/actions/App/Http/Controllers/Member/BasketController';
import WalletController from '@/actions/App/Http/Controllers/WalletController';
import Heading from '@/components/Heading.vue';
import AmountAdjustments from '@/components/baskets/AmountAdjustments.vue';
import BasketCollectionHistory from '@/components/baskets/BasketCollectionHistory.vue';
import WeeklyLines from '@/components/baskets/WeeklyLines.vue';
import StockLines from '@/components/pickup/StockLines.vue';
import ReturnLines from '@/components/pickup/ReturnLines.vue';
import { euros } from '@/components/pickup/euros';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

const props = defineProps<{ basket: App.Http.BasketRow }>();
const page = usePage();
const errors = computed(
    () => (page.props.errors ?? {}) as Record<string, string>,
);
const processing = ref(false);
const collect = () =>
    router.put(
        BasketCollectionController.update.url({ basket: props.basket.id }),
        {},
        {
            preserveScroll: true,
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
        },
    );
const notices: Record<string, { test: string; text: string }> = {
    draft: {
        test: 'draft-preparation',
        text: trans('Draft basket in preparation.'),
    },
    reversed: {
        test: 'goods-reversed',
        text: trans('All goods were returned; nothing is left to collect.'),
    },
    collected: {
        test: 'collected',
        text: trans('This basket was collected.'),
    },
    nothing_to_collect: {
        test: 'nothing-to-collect',
        text: trans('There is nothing to collect in this basket.'),
    },
    cancelled: {
        test: 'cancelled',
        text: trans('This draft was cancelled.'),
    },
};
const statusLabels: Record<string, string> = {
    draft: trans('In preparation'),
    nothing_to_collect: trans('Nothing to collect'),
    reversed: trans('Goods fully reversed'),
    validated: trans('Pending collection'),
    collected: trans('Collected'),
    cancelled: trans('Cancelled basket'),
};
</script>

<template>
    <Head :title="trans('Basket: :name', { name: basket.householdName })" />
    <div class="pilot-page pilot-layout-page">
        <Heading
            :title="trans('Basket: :name', { name: basket.householdName })"
            :description="
                !basket.weekly
                    ? trans('Independent basket')
                    : basket.late
                      ? trans('Late basket')
                      : trans('Weekly basket')
            "
        />
        <Link
            :href="BasketController.index.url()"
            class="pilot-back-link inline-flex min-h-11 items-center text-sm underline"
            data-visual-page-focus-first
        >
            {{ trans('All baskets') }}
        </Link>
        <Badge
            variant="secondary"
            :data-test="`basket-status-${basket.status}`"
            >{{ statusLabels[basket.status] ?? basket.status }}</Badge
        >
        <WeeklyLines
            v-if="basket.weekly"
            :lines="basket.weeklyLines"
            :editable="false"
            :processing="false"
        />
        <StockLines
            :lines="basket.stockLines"
            :options="[]"
            :editable="false"
            :processing="false"
            :empty-message="trans('No stock has been added to this basket.')"
        />
        <ReturnLines
            :lines="basket.returnLines"
            :options="[]"
            :editable="false"
            :processing="false"
            :empty-message="trans('No returns have been added to this basket.')"
        />
        <section class="pilot-layout-panel">
            <dl
                class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3"
                data-test="basket-balance"
            >
                <div>
                    <dt class="text-muted-foreground">
                        {{ trans('Current balance') }}
                    </dt>
                    <dd>{{ euros(basket.balanceCents) }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">
                        {{ trans('Basket total') }}
                    </dt>
                    <dd>{{ euros(basket.totalCents) }}</dd>
                </div>
                <div v-if="basket.projectedBalanceCents !== null">
                    <dt class="text-muted-foreground">
                        {{ trans('Projected balance') }}
                    </dt>
                    <dd>{{ euros(basket.projectedBalanceCents) }}</dd>
                </div>
            </dl>
            <Link
                :href="WalletController.show.url()"
                class="inline-flex min-h-11 items-center text-sm underline"
                data-test="wallet-link"
                :data-visual-page-focus-last="
                    basket.can.markCollected ? undefined : ''
                "
            >
                {{ trans('View household Wallet statement') }}
            </Link>
        </section>
        <AmountAdjustments
            v-if="basket.settled"
            :original-cents="basket.totalCents"
            :effective-cents="basket.effectiveTotalCents"
            :adjustments="basket.adjustments"
            :stock-lines="basket.stockLines"
            :editable="false"
        />
        <BasketCollectionHistory
            v-if="basket.settled"
            :collected="basket.collected"
            :events="basket.collectionEvents ?? []"
            :editable="false"
        />
        <div
            v-if="basket.can.markCollected"
            class="flex flex-col items-start gap-2"
            data-test="pending-collection"
        >
            <p>{{ trans('Validated and pending physical collection.') }}</p>
            <Button
                type="button"
                class="min-h-11"
                :disabled="processing"
                data-test="collect-basket"
                data-visual-page-focus-last
                @click="collect"
            >
                {{ trans('Mark collected') }}
            </Button>
            <InputError :message="errors.domain" data-test="domain-error" />
        </div>
        <p v-else :data-test="notices[basket.status]?.test">
            {{ notices[basket.status]?.text }}
        </p>
    </div>
</template>

<style scoped>
:deep(.pilot-heading) {
    overflow-wrap: anywhere;
}
</style>
