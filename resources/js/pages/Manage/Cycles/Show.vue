<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import CycleController from '@/actions/App/Http/Controllers/Manage/CycleController';
import Heading from '@/components/Heading.vue';

defineProps<{
    summary: App.Http.CycleSummaryRow;
    containers: App.Http.ContainerCountRow;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Cycles', href: CycleController.index() }],
    },
});
</script>

<template>
    <Head :title="`Cycle ${summary.pickupDate}`" />

    <div class="pilot-page pilot-manager-page">
        <Heading
            :title="`Pickup ${summary.pickupDate}`"
            description="What was ordered, per producer and product"
        />

        <p
            v-if="summary.cancelled"
            class="text-sm text-muted-foreground"
            data-test="cancelled-notice"
        >
            Cancelled. This cycle has no totals.
        </p>

        <p
            v-else-if="summary.producers.length === 0"
            class="text-sm text-muted-foreground"
            data-test="no-orders"
        >
            Nobody has ordered yet.
        </p>

        <section
            v-if="!summary.cancelled"
            class="flex flex-col gap-3"
            data-test="container-counts"
        >
            <h2 class="text-lg font-medium">
                {{ trans('Containers to collect') }}
            </h2>
            <p data-test="container-total">
                {{ trans('Cycle total') }}: {{ containers.total }}
            </p>
            <ul class="divide-y rounded-lg border">
                <li
                    v-for="household in containers.households"
                    :key="household.householdId"
                    class="flex items-center justify-between gap-3 p-3"
                    :data-test="`container-household-${household.householdId}`"
                >
                    <span class="min-w-0 break-words">{{
                        household.householdName
                    }}</span>
                    <span class="shrink-0">{{ household.count }}</span>
                </li>
            </ul>
        </section>

        <section
            v-for="producer in summary.producers"
            :key="producer.producerId"
            class="flex flex-col gap-3"
            :data-test="`producer-${producer.producerId}`"
        >
            <h2 class="text-lg font-medium">{{ producer.producerName }}</h2>

            <ul class="divide-y rounded-lg border">
                <li
                    v-for="product in producer.products"
                    :key="product.productId"
                    class="flex flex-col gap-2 p-3"
                    :data-test="`product-${product.productId}`"
                >
                    <div class="flex justify-between gap-2 font-medium">
                        <span>{{ product.name }}</span>
                        <span>{{ product.quantity }} {{ product.unit }}</span>
                    </div>
                    <ul class="text-sm text-muted-foreground">
                        <li
                            v-for="household in product.households"
                            :key="household.householdId"
                            class="flex justify-between gap-2"
                        >
                            <span>{{ household.householdName }}</span>
                            <span>{{ household.quantity }}</span>
                        </li>
                    </ul>
                </li>
            </ul>
        </section>
    </div>
</template>
