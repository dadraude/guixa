<script setup lang="ts">
import { formatPickupDate, formatQuantity } from '@/lib/locale';
import { trans } from 'laravel-vue-i18n';
import { Form, Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import StockMovementController from '@/actions/App/Http/Controllers/Manage/StockMovementController';
import StockProductController from '@/actions/App/Http/Controllers/Manage/StockProductController';
import FormSelect from '@/components/FormSelect.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import MovementHistory from '@/components/stock/MovementHistory.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Stock', href: StockProductController.index() },
            { title: 'Movements', href: '#' },
        ],
    },
});

const visit = (url: string) => router.visit(url);

const props = defineProps<{
    product: App.Http.StockLevelRow;
    cycles: App.Http.CycleOptionRow[];
    history: App.Http.StockMovementPageRow;
}>();

const selectedAdjustmentCycleId = ref<string | null>(null);
const cycleOptions = computed(() =>
    props.cycles.map((cycle) => ({
        value: cycle.id,
        label: trans('Pickup :date', {
            date: formatPickupDate(cycle.pickupDate),
        }),
    })),
);
const selectedAdjustmentCycleLabel = computed(
    () =>
        cycleOptions.value.find(
            (option) => option.value === selectedAdjustmentCycleId.value,
        )?.label ?? '',
);
</script>

<template>
    <Head :title="trans('Stock movements')" />

    <div class="pilot-page pilot-layout-page pilot-manager-page pilot-t44-page">
        <Heading
            :title="product.name"
            :description="
                trans(':quantity :unit available', {
                    quantity: formatQuantity(product.available),
                    unit: product.unit,
                })
            "
        />

        <Form
            v-bind="StockMovementController.storeEntry.form(product.id)"
            class="space-y-6"
            reset-on-success
            v-slot="{ errors, processing }"
        >
            <h2 class="text-lg font-medium">{{ trans('Record an entry') }}</h2>
            <InputError :message="errors.domain" />

            <div class="grid gap-2">
                <Label for="entry-quantity">{{ trans('Quantity') }}</Label>
                <Input
                    id="entry-quantity"
                    name="quantity"
                    inputmode="decimal"
                    required
                    placeholder="10"
                />
                <InputError :message="errors.quantity" />
            </div>

            <div class="grid gap-2">
                <Label for="entry-note">{{ trans('Note (optional)') }}</Label>
                <Input
                    id="entry-note"
                    name="note"
                    :placeholder="trans('Bought from X')"
                />
                <InputError :message="errors.note" />
            </div>

            <Button
                type="submit"
                :disabled="processing"
                data-test="record-entry"
            >
                {{ trans('Record entry') }}
            </Button>
        </Form>

        <Form
            v-bind="StockMovementController.storeAdjustment.form(product.id)"
            class="space-y-6"
            reset-on-success
            @reset="selectedAdjustmentCycleId = null"
            v-slot="{ errors, processing }"
        >
            <h2 class="text-lg font-medium">
                {{ trans('Record an adjustment') }}
            </h2>
            <InputError :message="errors.domain" />

            <div class="grid gap-2">
                <Label for="adjustment-quantity">
                    {{ trans('Quantity (negative to take stock out)') }}
                </Label>
                <Input
                    id="adjustment-quantity"
                    name="quantity"
                    inputmode="decimal"
                    required
                    placeholder="-1"
                />
                <InputError :message="errors.quantity" />
            </div>

            <div class="grid gap-2">
                <Label for="adjustment-note">{{ trans('Note') }}</Label>
                <Input
                    id="adjustment-note"
                    name="note"
                    required
                    :placeholder="trans('Broken jar, inventory count')"
                />
                <InputError :message="errors.note" />
            </div>

            <div class="grid min-w-0 grid-cols-1 gap-2">
                <Label for="adjustment-cycle">{{
                    trans('Cycle (optional)')
                }}</Label>
                <FormSelect
                    id="adjustment-cycle"
                    v-model="selectedAdjustmentCycleId"
                    name="cycle_id"
                    :options="cycleOptions"
                    :placeholder="trans('No cycle')"
                    class="w-full"
                />
                <output
                    v-if="selectedAdjustmentCycleId"
                    for="adjustment-cycle"
                    data-test="adjustment-cycle-preview"
                    class="block w-full text-base break-words text-muted-foreground"
                >
                    {{ selectedAdjustmentCycleLabel }}
                </output>
                <Button
                    v-if="selectedAdjustmentCycleId"
                    type="button"
                    variant="outline"
                    class="min-h-11 w-fit"
                    data-test="clear-adjustment-cycle"
                    @click="selectedAdjustmentCycleId = null"
                >
                    {{ trans('No cycle') }}
                </Button>
                <InputError :message="errors.cycle_id" />
            </div>

            <Button
                type="submit"
                :disabled="processing"
                data-test="record-adjustment"
            >
                {{ trans('Record adjustment') }}
            </Button>
        </Form>

        <MovementHistory :history="history" @navigate="visit" />
    </div>
</template>
