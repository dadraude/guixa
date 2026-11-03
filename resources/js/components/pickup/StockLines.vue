<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import FormSelect from '@/components/FormSelect.vue';
import { euros } from './euros';
import { formatQuantity } from '@/lib/locale';

const props = defineProps<{
    lines: App.Http.BasketStockLineRow[];
    options: App.Http.StockLevelRow[];
    editable: boolean;
    processing: boolean;
    errors?: Record<string, string>;
    emptyMessage?: string;
}>();

const emit = defineEmits<{
    add: [stockProductId: string, quantity: string];
    remove: [stockProductId: string];
}>();

const productId = ref('');
const quantity = ref('');

const productOptions = computed(() =>
    props.options.map((option) => ({
        value: option.id,
        label: `${option.name} (${formatQuantity(option.available)} ${option.unit})`,
    })),
);

/** Only corrected basket lines carry an effective quantity that differs from the original. */
const corrected = (line: (typeof props.lines)[number]) =>
    'effectiveQuantity' in line && line.effectiveQuantity !== line.quantity
        ? line
        : null;

const submit = () => {
    if (productId.value === '' || quantity.value.trim() === '') {
        return;
    }

    emit('add', productId.value, quantity.value.trim());
    quantity.value = '';
};
</script>

<template>
    <section class="flex flex-col gap-4" data-test="stock-lines">
        <h3 class="text-base font-medium">{{ trans('Stock') }}</h3>

        <p
            v-if="lines.length === 0"
            class="text-sm text-muted-foreground"
            data-test="no-stock-lines"
        >
            {{
                emptyMessage ?? trans('No stock has been added to this pickup.')
            }}
        </p>

        <ul class="pilot-list">
            <li
                v-for="line in lines"
                :key="line.stockProductId"
                class="pilot-list-row"
                :data-test="`stock-line-${line.stockProductId}`"
            >
                <div class="pilot-list-row-body">
                    <span>{{ line.name }}</span>
                    <span class="text-xs text-muted-foreground">
                        {{ formatQuantity(line.quantity) }} {{ line.unit }} ·
                        {{ euros(line.unitPriceCents) }} / {{ line.unit }}
                    </span>
                    <span
                        v-if="corrected(line)"
                        class="text-xs text-muted-foreground"
                        data-test="effective-quantity"
                    >
                        {{
                            trans('Effective quantity: :quantity :unit', {
                                quantity: formatQuantity(
                                    corrected(line)!.effectiveQuantity,
                                ),
                                unit: line.unit,
                            })
                        }}
                    </span>
                </div>
                <span class="pilot-list-figures">
                    <span
                        class="pilot-amount"
                        :class="{
                            'text-muted-foreground line-through':
                                corrected(line),
                        }"
                        data-test="amount"
                        >{{ euros(line.amountCents) }}</span
                    >
                    <span
                        v-if="corrected(line)"
                        class="pilot-amount"
                        data-test="effective-amount"
                        >{{
                            euros(corrected(line)!.effectiveAmountCents)
                        }}</span
                    >
                </span>
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
            <FormSelect
                v-model="productId"
                :options="productOptions"
                :placeholder="trans('Choose a product')"
                class="h-11 min-w-0 flex-1 basis-44"
                :aria-label="trans('Stock product')"
                data-test="stock-product"
            />
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
