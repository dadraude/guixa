<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatMoney } from '@/lib/locale';
import type { OrderRow } from '@/lib/orderRows';

defineProps<{
    row: OrderRow;
    quantity: string;
    amount: string;
    open: boolean;
    saving: boolean;
    dirty: boolean;
}>();

const emit = defineEmits<{
    quantity: [value: string];
    save: [];
    remove: [];
}>();
</script>

<template>
    <li class="@container min-w-0">
        <div
            class="grid min-w-0 gap-4 p-4 @2xl:grid-cols-[minmax(0,1fr)_8rem_7rem_6rem] @2xl:items-end @2xl:p-5"
        >
            <div class="min-w-0 space-y-1 @2xl:self-center">
                <h3 class="font-semibold wrap-anywhere">{{ row.name }}</h3>
                <p class="text-sm text-muted-foreground">
                    {{ formatMoney(row.price) }} / {{ row.unit }}
                </p>
                <p
                    v-if="row.notDelivered"
                    class="text-sm font-medium"
                    :data-test="`not-delivered-${row.productId}`"
                >
                    {{ trans('Will not be delivered') }}
                </p>
            </div>
            <div class="space-y-2">
                <label
                    :for="`quantity-${row.productId}`"
                    class="text-sm font-medium"
                >
                    {{ trans('Quantity') }}
                </label>
                <Input
                    :id="`quantity-${row.productId}`"
                    :aria-label="`${trans('Quantity')} — ${row.name}`"
                    :model-value="quantity"
                    :disabled="!open || row.notDelivered"
                    :inputmode="row.variable ? 'decimal' : 'numeric'"
                    class="h-11 text-base md:text-base"
                    :data-test="`quantity-${row.productId}`"
                    @update:model-value="emit('quantity', String($event))"
                    @keydown.enter="emit('save')"
                />
            </div>
            <div
                class="flex items-center justify-between gap-2 @2xl:block @2xl:space-y-2 @2xl:text-right"
            >
                <span class="text-sm text-muted-foreground">{{
                    trans('Amount')
                }}</span>
                <p class="font-semibold tabular-nums @2xl:min-h-11 @2xl:pt-2.5">
                    {{ formatMoney(amount) }}
                </p>
            </div>
            <Button
                v-if="row.notDelivered"
                variant="outline"
                class="min-h-11 min-w-11"
                :disabled="saving"
                :aria-label="`${trans('Remove')} — ${row.name}`"
                :data-test="`remove-${row.productId}`"
                @click="emit('remove')"
            >
                {{ trans('Remove') }}
            </Button>
            <Button
                v-else
                class="min-h-11 min-w-11"
                :disabled="!open || saving || !dirty"
                :aria-label="`${trans('Save')} — ${row.name}`"
                :data-test="`save-${row.productId}`"
                @click="emit('save')"
            >
                {{ trans('Save') }}
            </Button>
        </div>
    </li>
</template>
