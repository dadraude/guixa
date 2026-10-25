<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import StockMovementController from '@/actions/App/Http/Controllers/Manage/StockMovementController';
import StockProductController from '@/actions/App/Http/Controllers/Manage/StockProductController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
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

defineProps<{
    product: App.Http.StockLevelRow;
    cycles: App.Http.CycleOptionRow[];
}>();

const selectedAdjustmentCycleLabel = ref('');

function updateSelectedCycle(event: Event): void {
    const select = event.target as HTMLSelectElement;
    selectedAdjustmentCycleLabel.value = select.value
        ? (select.selectedOptions[0]?.textContent?.trim() ?? '')
        : '';
}
</script>

<template>
    <Head title="Stock movements" />

    <div class="pilot-page pilot-manager-page max-w-xl">
        <Heading
            :title="product.name"
            :description="`${product.available} ${product.unit} available`"
        />

        <Form
            v-bind="StockMovementController.storeEntry.form(product.id)"
            class="space-y-4"
            reset-on-success
            v-slot="{ errors, processing }"
        >
            <h2 class="text-lg font-medium">Record an entry</h2>
            <InputError :message="errors.domain" />

            <div class="grid gap-2">
                <Label for="entry-quantity">Quantity</Label>
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
                <Label for="entry-note">Note (optional)</Label>
                <Input
                    id="entry-note"
                    name="note"
                    placeholder="Bought from X"
                />
                <InputError :message="errors.note" />
            </div>

            <Button
                type="submit"
                :disabled="processing"
                data-test="record-entry"
            >
                Record entry
            </Button>
        </Form>

        <Form
            v-bind="StockMovementController.storeAdjustment.form(product.id)"
            class="space-y-4"
            reset-on-success
            @reset="selectedAdjustmentCycleLabel = ''"
            v-slot="{ errors, processing }"
        >
            <h2 class="text-lg font-medium">Record an adjustment</h2>
            <InputError :message="errors.domain" />

            <div class="grid gap-2">
                <Label for="adjustment-quantity">
                    Quantity (negative to take stock out)
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
                <Label for="adjustment-note">Note</Label>
                <Input
                    id="adjustment-note"
                    name="note"
                    required
                    placeholder="Broken jar, inventory count"
                />
                <InputError :message="errors.note" />
            </div>

            <div class="grid min-w-0 grid-cols-1 gap-2">
                <Label for="adjustment-cycle">Cycle (optional)</Label>
                <select
                    id="adjustment-cycle"
                    name="cycle_id"
                    class="h-9 w-full min-w-0 rounded-md border bg-background px-2 text-sm"
                    @change="updateSelectedCycle"
                >
                    <option value="">No cycle</option>
                    <option
                        v-for="cycle in cycles"
                        :key="cycle.id"
                        :value="cycle.id"
                    >
                        Pickup {{ cycle.pickupDate }}
                    </option>
                </select>
                <output
                    v-if="selectedAdjustmentCycleLabel"
                    for="adjustment-cycle"
                    data-test="adjustment-cycle-preview"
                    class="block w-full text-base break-words text-muted-foreground"
                >
                    {{ selectedAdjustmentCycleLabel }}
                </output>
                <InputError :message="errors.cycle_id" />
            </div>

            <Button
                type="submit"
                :disabled="processing"
                data-test="record-adjustment"
            >
                Record adjustment
            </Button>
        </Form>
    </div>
</template>
