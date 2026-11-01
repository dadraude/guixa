<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import BasketCollectionController from '@/actions/App/Http/Controllers/Finance/BasketCollectionController';
import BasketController from '@/actions/App/Http/Controllers/Finance/BasketController';
import BasketStockController from '@/actions/App/Http/Controllers/Finance/BasketStockController';
import BasketReturnController from '@/actions/App/Http/Controllers/Finance/BasketReturnController';
import BasketValidationController from '@/actions/App/Http/Controllers/Finance/BasketValidationController';
import HouseholdWalletController from '@/actions/App/Http/Controllers/Finance/HouseholdWalletController';
import Heading from '@/components/Heading.vue';
import AmountAdjustments from '@/components/baskets/AmountAdjustments.vue';
import InputError from '@/components/InputError.vue';
import StockLines from '@/components/pickup/StockLines.vue';
import ReturnLines from '@/components/pickup/ReturnLines.vue';
import { euros } from '@/components/pickup/euros';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTemplateRef } from 'vue';

const props = defineProps<{
    basket: App.Http.BasketRow;
    stockProducts: App.Http.StockLevelRow[];
    returnItems: App.Http.ReturnItemRow[];
}>();
const page = usePage();
const errors = computed(() => page.props.errors as Record<string, string>);
const processing = ref(false);
const adjustmentPanel = useTemplateRef('adjustmentPanel');
const options = {
    preserveScroll: true,
    onStart: () => (processing.value = true),
    onFinish: () => (processing.value = false),
};
const addStock = (stockProductId: string, quantity: string) =>
    router.post(
        BasketStockController.store.url({ basket: props.basket.id }),
        { stock_product_id: stockProductId, stock_quantity: quantity },
        options,
    );
const removeStock = (stockProductId: string) =>
    router.delete(
        BasketStockController.destroy.url({
            basket: props.basket.id,
            stockProduct: stockProductId,
        }),
        options,
    );
const addReturn = (returnItemId: string, quantity: string) =>
    router.post(
        BasketReturnController.store.url({ basket: props.basket.id }),
        { return_item_id: returnItemId, return_quantity: quantity },
        options,
    );
const removeReturn = (returnItemId: string) =>
    router.delete(
        BasketReturnController.destroy.url({
            basket: props.basket.id,
            returnItem: returnItemId,
        }),
        options,
    );
const validate = () =>
    router.post(
        BasketValidationController.store.url({ basket: props.basket.id }),
        {},
        options,
    );
const collect = () =>
    router.put(
        BasketCollectionController.update.url({ basket: props.basket.id }),
        {},
        options,
    );
const cancel = () =>
    router.post(
        BasketController.cancel.url({ basket: props.basket.id }),
        {},
        options,
    );
const correct = (amountCents: number, reason: string, correctionId: string) =>
    router.post(
        `/finance/baskets/${props.basket.id}/corrections`,
        { amount_cents: amountCents, reason, correction_id: correctionId },
        { ...options, onSuccess: () => adjustmentPanel.value?.reset() },
    );
const statusLabels: Record<string, string> = {
    draft: trans('Draft'),
    validated: trans('Pending collection'),
    collected: trans('Collected'),
    cancelled: trans('Cancelled'),
};
</script>

<template>
    <Head :title="trans('Basket: :name', { name: basket.householdName })" />
    <div class="pilot-page pilot-layout-page pilot-manager-page">
        <Heading
            :title="trans('Basket: :name', { name: basket.householdName })"
            :description="trans('Independent basket')"
        />
        <Link
            :href="BasketController.index.url()"
            class="pilot-back-link text-sm underline"
            data-visual-page-focus-first
        >
            {{ trans('All baskets') }}
        </Link>
        <InputError :message="errors.domain" data-test="domain-error" />
        <Badge
            variant="secondary"
            :data-test="`basket-status-${basket.state}`"
            >{{ statusLabels[basket.state] ?? basket.state }}</Badge
        >

        <StockLines
            :lines="basket.stockLines"
            :options="stockProducts"
            :editable="basket.state === 'draft'"
            :processing="processing"
            :errors="errors"
            :empty-message="trans('No stock has been added to this basket.')"
            @add="addStock"
            @remove="removeStock"
        />

        <ReturnLines
            :lines="basket.returnLines"
            :options="returnItems"
            :editable="basket.state === 'draft'"
            :processing="processing"
            :errors="errors"
            :empty-message="trans('No returns have been added to this basket.')"
            @add="addReturn"
            @remove="removeReturn"
        />

        <section class="pilot-layout-panel">
            <dl
                class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-3"
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
                :href="
                    HouseholdWalletController.show.url({
                        household: basket.householdId,
                    })
                "
                class="text-sm underline"
                data-test="wallet-link"
            >
                {{ trans('View household Wallet statement') }}
            </Link>
        </section>

        <AmountAdjustments
            v-if="basket.state === 'validated' || basket.state === 'collected'"
            ref="adjustmentPanel"
            :original-cents="basket.totalCents"
            :effective-cents="basket.effectiveTotalCents"
            :adjustments="basket.adjustments"
            :editable="true"
            :processing="processing"
            :errors="errors"
            @submit="correct"
        />

        <div
            v-if="basket.state === 'draft'"
            class="flex flex-wrap gap-2"
            data-test="draft-actions"
        >
            <Button
                type="button"
                class="min-h-11"
                :disabled="
                    processing ||
                    (basket.stockLines.length === 0 &&
                        basket.returnLines.length === 0)
                "
                data-test="validate-basket"
                @click="validate"
                data-visual-page-focus-last
            >
                {{ trans('Validate basket') }}
            </Button>
            <Button
                type="button"
                variant="outline"
                class="min-h-11"
                :disabled="processing"
                @click="cancel"
                >{{ trans('Cancel draft') }}</Button
            >
        </div>
        <div
            v-else-if="basket.state === 'validated'"
            class="flex flex-col items-start gap-2"
            data-test="pending-collection"
        >
            <p class="text-sm text-muted-foreground">
                {{ trans('Validated and pending physical collection.') }}
            </p>
            <Button
                type="button"
                class="min-h-11"
                :disabled="processing"
                data-test="collect-basket"
                @click="collect"
                data-visual-page-focus-last
            >
                {{ trans('Mark collected') }}
            </Button>
        </div>
        <p
            v-else-if="basket.state === 'collected'"
            class="text-sm text-muted-foreground"
            data-test="collected"
            data-visual-page-focus-last
        >
            {{ trans('This basket was collected.') }}
        </p>
        <p
            v-else
            class="text-sm text-muted-foreground"
            data-test="cancelled"
            data-visual-page-focus-last
        >
            {{ trans('This draft was cancelled.') }}
        </p>
    </div>
</template>

<style scoped>
.pilot-manager-page :deep(.pilot-heading) {
    overflow-wrap: anywhere;
}
</style>
