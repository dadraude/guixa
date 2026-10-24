<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import CycleController from '@/actions/App/Http/Controllers/Manage/CycleController';
import PickupController from '@/actions/App/Http/Controllers/Manage/PickupController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    cycleId: string;
    pickups: App.Http.PickupSummaryRow[];
    households: App.Http.HouseholdOptionRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Cycles', href: CycleController.index() }],
    },
});

const statusLabels: Record<string, string> = {
    pending: 'Pending',
    nothing_to_collect: 'Nothing to collect',
};

const euros = (cents: number) =>
    `${(cents / 100).toFixed(2).replace('.', ',')} €`;

const openForm = (householdId: string) =>
    PickupController.store.form({
        cycle: props.cycleId,
        household: householdId,
    });
</script>

<template>
    <Head title="Pickups" />

    <div class="flex flex-col gap-8 p-4">
        <Heading
            title="Pickups"
            description="Households collect their allocation and stock products."
        />

        <p
            v-if="pickups.length === 0"
            class="text-sm text-muted-foreground"
            data-test="no-pickups"
        >
            No household has a pickup in this cycle yet.
        </p>

        <ul class="divide-y">
            <li
                v-for="pickup in pickups"
                :key="pickup.householdId"
                class="flex flex-wrap items-center justify-between gap-2 py-2"
                :data-test="`pickup-${pickup.householdId}`"
            >
                <Link
                    :href="
                        PickupController.show.url({
                            cycle: cycleId,
                            household: pickup.householdId,
                        })
                    "
                    class="underline"
                >
                    {{ pickup.householdName }}
                </Link>
                <Badge variant="secondary">
                    {{ statusLabels[pickup.status] ?? pickup.status }}
                </Badge>
                <span>{{ euros(pickup.totalCents) }}</span>
            </li>
        </ul>

        <section v-if="households.length > 0" class="flex flex-col gap-2">
            <h2 class="text-lg font-medium">Open a pickup</h2>
            <p class="text-sm text-muted-foreground">
                For a household without an order, for example to sell it stock.
            </p>
            <ul class="divide-y">
                <li
                    v-for="household in households"
                    :key="household.id"
                    class="flex items-center justify-between gap-2 py-2"
                    :data-test="`open-${household.id}`"
                >
                    <span>{{ household.name }}</span>
                    <Form v-bind="openForm(household.id)">
                        <Button type="submit" variant="outline" size="sm">
                            Open pickup
                        </Button>
                    </Form>
                </li>
            </ul>
        </section>
    </div>
</template>
