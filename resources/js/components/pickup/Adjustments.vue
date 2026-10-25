<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { euros } from './euros';

defineProps<{
    lines: App.Http.PickupAdjustmentRow[];
    originalChargeCents: number | null;
}>();
</script>

<template>
    <section class="flex flex-col gap-3" data-test="pickup-adjustments">
        <p v-if="originalChargeCents !== null" class="text-sm">
            {{ trans('Original charge') }}: {{ euros(originalChargeCents) }}
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
            <ul class="divide-y">
                <li
                    v-for="line in lines"
                    :key="line.id"
                    class="flex flex-wrap justify-between gap-2 py-3"
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
                    <template v-else>
                        <span>{{ line.quantityDelta }} {{ line.unit }}</span>
                        <span>{{ euros(line.amountCents) }}</span>
                    </template>
                </li>
            </ul>
        </template>
    </section>
</template>
