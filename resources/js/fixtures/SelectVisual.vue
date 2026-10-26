<script setup lang="ts">
import { onMounted, ref } from 'vue';
import FormSelect from '@/components/FormSelect.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

const query = new URLSearchParams(window.location.search);
const long = query.has('long');
const disabled = query.has('disabled');
const product = ref('');
const cycle = ref<string | null>(null);

const householdOptions = [
    { value: 'north', label: 'North household' },
    {
        value: 'south',
        label: long
            ? 'South household with a very long cooperative member name and address'
            : 'South household',
    },
];
const weekdayOptions = [
    { value: '1', label: 'Monday' },
    { value: '2', label: 'Tuesday' },
    { value: '3', label: 'Wednesday' },
];
const productOptions = [
    { value: 'cheese', label: 'Cheese (3.500 kg)' },
    { value: 'honey', label: 'Honey (5 jars)' },
    { value: 'bread', label: 'Bread (12 loaves)' },
];

onMounted(() => {
    if (query.has('open')) {
        requestAnimationFrame(() => {
            document
                .querySelector<HTMLButtonElement>('#fixture-product')
                ?.dispatchEvent(
                    new KeyboardEvent('keydown', {
                        key: 'ArrowDown',
                        bubbles: true,
                    }),
                );
        });
    }
});
</script>

<template>
    <main
        class="mx-auto grid max-w-4xl min-w-0 gap-6 p-4 text-foreground sm:p-8"
        data-visual-root
    >
        <header class="grid gap-1">
            <h1 class="text-2xl font-semibold">Select controls</h1>
            <p class="text-sm text-muted-foreground">
                Manager forms and pickup controls
            </p>
        </header>

        <section
            class="visual-panel grid min-w-0 gap-4 rounded-lg border bg-card p-4"
        >
            <h2 class="text-lg font-medium">Settings</h2>
            <form class="flex flex-wrap items-end gap-2">
                <div class="grid gap-2">
                    <Label for="fixture-weekday">Pickup weekday</Label>
                    <FormSelect
                        id="fixture-weekday"
                        name="pickup_weekday"
                        :options="weekdayOptions"
                        default-value="2"
                        class="w-full min-w-0 sm:w-fit sm:min-w-44"
                    />
                </div>
                <Button type="button">Save</Button>
            </form>
        </section>

        <section
            class="visual-panel grid min-w-0 gap-4 rounded-lg border bg-card p-4"
        >
            <h2 class="text-lg font-medium">Accounts and households</h2>
            <form class="flex flex-wrap items-center gap-2">
                <FormSelect
                    id="fixture-household"
                    name="household_id"
                    required
                    :options="householdOptions"
                    default-value="south"
                    class="min-w-0 flex-1 basis-44"
                    aria-label="Household for member"
                />
                <Button type="button" variant="outline">Move</Button>
            </form>
        </section>

        <section
            class="visual-panel grid min-w-0 gap-4 rounded-lg border bg-card p-4"
        >
            <h2 class="text-lg font-medium">Pickup stock</h2>
            <form class="flex flex-wrap items-end gap-2">
                <FormSelect
                    id="fixture-product"
                    v-model="product"
                    :options="productOptions"
                    placeholder="Choose a product"
                    class="h-11 min-w-0 flex-1 basis-44"
                    aria-label="Stock product"
                />
                <Button type="button" variant="outline">Add stock</Button>
            </form>
        </section>

        <section
            class="visual-panel grid min-w-0 gap-4 rounded-lg border bg-card p-4"
        >
            <h2 class="text-lg font-medium">Stock adjustment</h2>
            <form class="grid gap-2">
                <Label for="fixture-cycle">Cycle (optional)</Label>
                <FormSelect
                    id="fixture-cycle"
                    v-model="cycle"
                    name="cycle_id"
                    :options="[
                        { value: 'november', label: 'Pickup 2026-11-07' },
                    ]"
                    placeholder="No cycle"
                    class="w-full"
                />
            </form>
        </section>

        <section
            class="visual-panel grid min-w-0 gap-4 rounded-lg border bg-card p-4"
        >
            <h2 class="text-lg font-medium">Disabled state</h2>
            <FormSelect
                id="fixture-disabled"
                :options="weekdayOptions"
                default-value="1"
                :disabled="disabled"
                aria-label="Disabled weekday"
                class="w-full min-w-0 sm:w-fit sm:min-w-44"
            />
        </section>
    </main>
</template>
