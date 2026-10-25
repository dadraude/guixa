<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { euros } from './euros';

defineProps<{
    lines: App.Http.PickupStockLineRow[];
    options: App.Http.StockLevelRow[];
    editable: boolean;
    processing: boolean;
    errors?: Record<string, string>;
}>();

const emit = defineEmits<{
    add: [stockProductId: string, quantity: string];
    remove: [stockProductId: string];
}>();

const productId = ref('');
const quantity = ref('');

const submit = () => {
    if (productId.value === '' || quantity.value.trim() === '') {
        return;
    }

    emit('add', productId.value, quantity.value.trim());
    quantity.value = '';
};
</script>

<template>
    <section class="flex flex-col gap-3" data-test="stock-lines">
        <h3 class="text-base font-medium">{{ trans('Stock') }}</h3>

        <p
            v-if="lines.length === 0"
            class="text-sm text-muted-foreground"
            data-test="no-stock-lines"
        >
            {{ trans('No stock has been added to this pickup.') }}
        </p>

        <ul class="divide-y">
            <li
                v-for="line in lines"
                :key="line.stockProductId"
                class="flex flex-wrap items-center justify-between gap-2 py-2"
                :data-test="`stock-line-${line.stockProductId}`"
            >
                <div class="flex min-w-0 flex-col">
                    <span>{{ line.name }}</span>
                    <span class="text-xs text-muted-foreground">
                        {{ line.quantity }} {{ line.unit }} ·
                        {{ euros(line.unitPriceCents) }} / {{ line.unit }}
                    </span>
                </div>
                <span data-test="amount">{{ euros(line.amountCents) }}</span>
                <Button
                    v-if="editable"
                    type="button"
                    variant="outline"
                    class="min-h-11 min-w-11"
                    :aria-label="trans('Remove :name', { name: line.name })"
                    :data-test="`remove-stock-${line.stockProductId}`"
                    :disabled="processing"
                    @click="emit('remove', line.stockProductId)"
                >
                    {{ trans('Remove') }}
                </Button>
            </li>
        </ul>

        <form
            v-if="editable"
            class="flex flex-wrap items-end gap-2"
            data-test="add-stock-form"
            @submit.prevent="submit"
        >
            <select
                v-model="productId"
                class="h-11 min-w-0 flex-1 rounded-md border border-input bg-transparent px-3 text-base"
                :aria-label="trans('Stock product')"
                data-test="stock-product"
            >
                <option value="" disabled>
                    {{ trans('Choose a product') }}
                </option>
                <option
                    v-for="option in options"
                    :key="option.id"
                    :value="option.id"
                >
                    {{ option.name }} ({{ option.available }} {{ option.unit }})
                </option>
            </select>
            <Input
                v-model="quantity"
                inputmode="decimal"
                class="h-11 w-28"
                :aria-label="trans('Quantity of stock to add')"
                data-test="stock-quantity"
            />
            <Button
                type="submit"
                variant="outline"
                class="min-h-11 min-w-11"
                :disabled="processing"
            >
                {{ trans('Add stock') }}
            </Button>
        </form>
        <InputError :message="errors?.stock_product_id" />
        <InputError
            :message="errors?.stock_quantity"
            data-test="quantity-error"
        />
    </section>
</template>
