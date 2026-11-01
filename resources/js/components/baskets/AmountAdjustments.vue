<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { shallowRef } from 'vue';
import InputError from '@/components/InputError.vue';
import { euros } from '@/components/pickup/euros';
import { Button } from '@/components/ui/button';

defineProps<{
    originalCents: number;
    effectiveCents: number;
    adjustments: App.Http.BasketAdjustmentRow[];
    editable: boolean;
    processing?: boolean;
    errors?: Record<string, string>;
}>();
const emit = defineEmits<{
    submit: [amountCents: number, reason: string, correctionId: string];
}>();
const amountCents = shallowRef('');
const reason = shallowRef('');
const correctionId = shallowRef(crypto.randomUUID());
function submit() {
    const amount = Number(amountCents.value);
    if (!Number.isInteger(amount) || amount === 0 || !reason.value.trim())
        return;
    emit('submit', amount, reason.value.trim(), correctionId.value);
}
function reset() {
    amountCents.value = '';
    reason.value = '';
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
