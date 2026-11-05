<script setup lang="ts">
import { formatPickupDate, formatQuantity } from '@/lib/locale';
import { Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import CycleController from '@/actions/App/Http/Controllers/Manage/CycleController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';

defineProps<{
    summary: App.Http.CycleSummaryRow;
    containers: App.Http.ContainerCountRow;
    pendingCollections: { count: number; href: string | null };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Cycles', href: CycleController.index() }],
    },
});
</script>

<template>
    <Head
        :title="
            trans('Cycle :date', { date: formatPickupDate(summary.pickupDate) })
        "
    />

    <div class="pilot-page pilot-layout-page pilot-manager-page">
        <Heading
            :title="
                trans('Pickup :date', {
                    date: formatPickupDate(summary.pickupDate),
                })
            "
            :description="trans('What was ordered, per producer and product')"
        />

        <Button
            v-if="pendingCollections.href !== null"
            as-child
            variant="outline"
            class="min-h-11 self-start"
            ><Link
                :href="pendingCollections.href"
                data-test="pending-collections"
                >{{
                    trans('Pending collections: :count', {
                        count: String(pendingCollections.count),
                    })
                }}</Link
            ></Button
        >

        <p
            v-if="summary.cancelled"
            class="text-sm text-muted-foreground"
            data-test="cancelled-notice"
        >
            {{ trans('Cancelled. This cycle has no totals.') }}
        </p>

        <p
            v-else-if="summary.producers.length === 0"
            class="text-sm text-muted-foreground"
            data-test="no-orders"
        >
            {{ trans('Nobody has ordered yet.') }}
        </p>

        <section
            v-if="!summary.cancelled"
            class="flex flex-col gap-4"
            data-test="container-counts"
        >
            <h2 class="text-lg font-medium">
                {{ trans('Containers to collect') }}
            </h2>
            <p data-test="container-total">
                {{
                    trans('Cycle total: :count', {
                        count: formatQuantity(String(containers.total)),
                    })
                }}
            </p>
            <ul class="pilot-list">
                <li
                    v-for="household in containers.households"
                    :key="household.householdId"
                    class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1"
                    :data-test="`container-household-${household.householdId}`"
                >
                    <span class="min-w-0 break-words">{{
                        household.householdName
                    }}</span>
                    <span class="shrink-0">{{
                        formatQuantity(String(household.count))
                    }}</span>
                    <span
                        class="min-w-0 break-words text-muted-foreground"
                        :data-test="`container-balance-${household.householdId}`"
                        >{{
                            trans('Containers to return: :count', {
                                count: formatQuantity(
                                    String(household.balance),
                                ),
                            })
                        }}</span
                    >
                </li>
            </ul>
        </section>

        <section
            v-for="producer in summary.producers"
            :key="producer.producerId"
            class="flex flex-col gap-4"
            :data-test="`producer-${producer.producerId}`"
        >
            <h2 class="text-lg font-medium">{{ producer.producerName }}</h2>

            <ul class="pilot-list">
                <li
                    v-for="product in producer.products"
                    :key="product.productId"
                    class="flex flex-col gap-2"
                    :data-test="`product-${product.productId}`"
                >
                    <div class="flex justify-between gap-2 font-medium">
                        <span>{{ product.name }}</span>
                        <span
                            >{{ formatQuantity(product.quantity) }}
                            {{ product.unit }}</span
                        >
                    </div>
                    <ul class="text-sm text-muted-foreground">
                        <li
                            v-for="household in product.households"
                            :key="household.householdId"
                            class="flex justify-between gap-2"
                        >
                            <span>{{ household.householdName }}</span>
                            <span>{{
                                formatQuantity(household.quantity)
                            }}</span>
                        </li>
                    </ul>
                </li>
            </ul>
        </section>
    </div>
</template>
