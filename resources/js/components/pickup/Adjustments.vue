<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { euros } from './euros';
import { formatQuantity } from '@/lib/locale';

defineProps<{
    lines: App.Http.PickupAdjustmentRow[];
    originalChargeCents: number | null;
}>();
</script>

<template>
    <section class="flex flex-col gap-4" data-test="pickup-adjustments">
        <p v-if="originalChargeCents !== null" class="pilot-total-row text-sm">
            <span>{{ trans('Original charge') }}:</span>
            <span class="pilot-amount" data-test="original-charge">{{
                euros(originalChargeCents)
            }}</span>
        </p>
        <template v-if="lines.length > 0">
            <h3 class="font-medium">{{ trans('Pickup adjustments') }}</h3>
            <p class="text-sm text-muted-foreground">
                {{
                    trans(
                        'These adjustments are already recorded in the wallet. Original pickup lines stay unchanged.',
                    )
                }}
            </p>
            <ul class="pilot-list">
                <li
                    v-for="line in lines"
                    :key="line.id"
                    class="pilot-list-row"
                    :data-test="`pickup-adjustment-${line.id}`"
                >
                    <span class="min-w-0 wrap-anywhere">{{ line.name }}</span>
                    <span
                        v-if="
                            line.quantityDelta === '0.000' &&
                            line.amountCents === 0
                        "
                        >{{
                            trans('Allocation reconciled; no extra charge')
                        }}</span
                    >
                    <span v-else class="pilot-list-figures">
                        <span
                            class="pilot-amount"
                            data-test="adjustment-quantity"
                            >{{ formatQuantity(line.quantityDelta) }}
                            {{ line.unit }}</span
                        >
                        <span
                            class="pilot-amount"
                            data-test="adjustment-amount"
                            >{{ euros(line.amountCents) }}</span
                        >
                    </span>
                </li>
            </ul>
        </template>
    </section>
</template>
