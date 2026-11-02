<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { computed, shallowRef } from 'vue';
import InputError from '@/components/InputError.vue';
import { euros } from '@/components/pickup/euros';
import { Button } from '@/components/ui/button';
import { formatQuantity } from '@/lib/locale';

const props = defineProps<{
    originalCents: number;
    effectiveCents: number;
    adjustments: App.Http.BasketAdjustmentRow[];
    editable: boolean;
    processing?: boolean;
    errors?: Record<string, string>;
    stockLines?: App.Http.BasketStockLineRow[];
}>();
const emit = defineEmits<{
    submit: [
        amountCents: number,
        reason: string,
        correctionId: string,
        returnedGoods: { stock_product_id: string; quantity: string }[],
    ];
}>();
const returned = shallowRef<Record<string, string>>({});
const returnable = computed(() =>
    (props.stockLines ?? []).filter(
        (line) => Number(line.effectiveQuantity) > 0,
    ),
);
const lineName = (productId: string) =>
    props.stockLines?.find((line) => line.stockProductId === productId);
const amountCents = shallowRef('');
const reason = shallowRef('');
const correctionId = shallowRef(crypto.randomUUID());
function submit() {
    const amount = Number(amountCents.value);
    if (!Number.isInteger(amount) || amount === 0 || !reason.value.trim())
        return;
    const returnedGoods = Object.entries(returned.value)
        .filter(([, quantity]) => quantity.trim() !== '')
        .map(([stock_product_id, quantity]) => ({
            stock_product_id,
            quantity: quantity.trim(),
        }));
    emit(
        'submit',
        amount,
        reason.value.trim(),
        correctionId.value,
        returnedGoods,
    );
}
function reset() {
    amountCents.value = '';
    reason.value = '';
    returned.value = {};
    correctionId.value = crypto.randomUUID();
}
defineExpose({ reset });
</script>

<template>
    <section
        class="pilot-layout-panel space-y-4"
        data-test="basket-adjustments"
    >
        <h2 class="font-medium">{{ trans('Basket amount corrections') }}</h2>
        <dl class="grid gap-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-muted-foreground">
                    {{ trans('Original basket total') }}
                </dt>
                <dd data-test="original-basket-total">
                    {{ euros(originalCents) }}
                </dd>
            </div>
            <div>
                <dt class="text-muted-foreground">
                    {{ trans('Effective basket total') }}
                </dt>
                <dd data-test="effective-basket-total">
                    {{ euros(effectiveCents) }}
                </dd>
            </div>
        </dl>
        <p
            v-if="adjustments.length === 0"
            class="text-sm text-muted-foreground"
        >
            {{ trans('No amount corrections recorded.') }}
        </p>
        <ol v-else class="pilot-list" data-test="basket-correction-history">
            <li
                v-for="adjustment in adjustments"
                :key="adjustment.id"
                class="pilot-list-row flex-wrap gap-2"
                :data-test="`basket-adjustment-${adjustment.id}`"
            >
                <span class="min-w-0 wrap-anywhere">{{
                    adjustment.reason
                }}</span>
                <span class="pilot-amount">{{
                    euros(adjustment.amountCents)
                }}</span>
                <ul
                    v-if="adjustment.lines.length > 0"
                    class="basis-full text-sm text-muted-foreground"
                    :data-test="`basket-adjustment-lines-${adjustment.id}`"
                >
                    <li
                        v-for="line in adjustment.lines"
                        :key="line.stockProductId"
                    >
                        {{ lineName(line.stockProductId)?.name ?? '' }}:
                        {{ formatQuantity(line.quantityDelta) }}
                        {{ lineName(line.stockProductId)?.unit ?? '' }}
                    </li>
                </ul>
                <span class="text-sm wrap-anywhere text-muted-foreground"
                    >{{ adjustment.recordedBy }} ·
                    {{ adjustment.occurredAt }}</span
                >
            </li>
        </ol>
        <form
            v-if="editable"
            class="space-y-3"
            data-test="basket-correction-form"
            @submit.prevent="submit"
        >
            <div class="space-y-1">
                <label for="correction-amount">{{
                    trans('Signed wallet correction (cents)')
                }}</label>
                <input
                    id="correction-amount"
                    v-model="amountCents"
                    type="number"
                    step="1"
                    required
                    class="min-h-11 w-full rounded-md border bg-background px-3"
                />
                <InputError :message="errors?.amount_cents" />
            </div>
            <fieldset
                v-if="returnable.length > 0"
                class="space-y-2"
                data-test="returned-goods"
            >
                <legend class="text-sm font-medium">
                    {{ trans('Goods physically returned') }}
                </legend>
                <p class="text-sm text-muted-foreground">
                    {{
                        trans(
                            'Leave empty for an amount-only correction. Returned goods go back to stock.',
                        )
                    }}
                </p>
                <div
                    v-for="line in returnable"
                    :key="line.stockProductId"
                    class="space-y-1"
                >
                    <label :for="`returned-${line.stockProductId}`">{{
                        trans('Returned :name (:unit)', {
                            name: line.name,
                            unit: line.unit,
                        })
                    }}</label>
                    <input
                        :id="`returned-${line.stockProductId}`"
                        v-model="returned[line.stockProductId]"
                        inputmode="decimal"
                        :placeholder="
                            trans('At most :quantity', {
                                quantity: formatQuantity(
                                    line.effectiveQuantity,
                                ),
                            })
                        "
                        class="min-h-11 w-full rounded-md border bg-background px-3"
                        :data-test="`returned-${line.stockProductId}`"
                    />
                </div>
                <InputError :message="errors?.returned_goods" />
            </fieldset>
            <div class="space-y-1">
                <label for="correction-reason">{{
                    trans('Reason for correction')
                }}</label>
                <textarea
                    id="correction-reason"
                    v-model="reason"
                    required
                    maxlength="2000"
                    class="min-h-24 w-full rounded-md border bg-background p-3"
                />
                <InputError :message="errors?.reason" />
            </div>
            <Button
                type="submit"
                class="min-h-11"
                :disabled="
                    processing ||
                    !reason.trim() ||
                    !Number.isInteger(Number(amountCents)) ||
                    Number(amountCents) === 0
                "
            >
                {{ trans('Record amount correction') }}
            </Button>
        </form>
    </section>
</template>
