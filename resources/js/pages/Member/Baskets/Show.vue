<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import BasketController from '@/actions/App/Http/Controllers/Member/BasketController';
import WalletController from '@/actions/App/Http/Controllers/WalletController';
import Heading from '@/components/Heading.vue';
import AmountAdjustments from '@/components/baskets/AmountAdjustments.vue';
import StockLines from '@/components/pickup/StockLines.vue';
import ReturnLines from '@/components/pickup/ReturnLines.vue';
import { euros } from '@/components/pickup/euros';
import { Badge } from '@/components/ui/badge';

defineProps<{ basket: App.Http.BasketRow }>();
const statusLabels: Record<string, string> = {
    draft: trans('In preparation'),
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
            :description="trans('Independent basket')"
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
            :data-test="`basket-status-${basket.state}`"
            >{{ statusLabels[basket.state] ?? basket.state }}</Badge
        >
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
                <div v-if="basket.state === 'draft'">
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
                data-visual-page-focus-last
            >
                {{ trans('View household Wallet statement') }}
            </Link>
        </section>
        <AmountAdjustments
            v-if="basket.state === 'validated' || basket.state === 'collected'"
            :original-cents="basket.totalCents"
            :effective-cents="basket.effectiveTotalCents"
            :adjustments="basket.adjustments"
            :editable="false"
        />
        <p v-if="basket.state === 'draft'" data-test="draft-preparation">
            {{ trans('Draft basket in preparation.') }}
        </p>
        <p
            v-else-if="basket.state === 'validated'"
            data-test="pending-collection"
        >
            {{ trans('Validated and pending physical collection.') }}
        </p>
        <p v-else-if="basket.state === 'collected'" data-test="collected">
            {{ trans('This basket was collected.') }}
        </p>
        <p v-else data-test="cancelled">
            {{ trans('This draft was cancelled.') }}
        </p>
    </div>
</template>

<style scoped>
:deep(.pilot-heading) {
    overflow-wrap: anywhere;
}
</style>
