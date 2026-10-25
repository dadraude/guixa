<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import ProductAvailabilityController from '@/actions/App/Http/Controllers/Producer/ProductAvailabilityController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';

defineProps<{
    summary: App.Http.ProducerCycleSummaryRow;
}>();
</script>

<template>
    <Head :title="`Cycle ${summary.pickupDate}`" />

    <div class="pilot-page">
        <Heading
            :title="`Pickup ${summary.pickupDate}`"
            :description="
                summary.sent
                    ? 'What to deliver, per product'
                    : 'Totals appear once the manager sends your order'
            "
        />

        <p
            v-if="summary.products.length === 0"
            class="pilot-empty-state text-sm text-muted-foreground"
            data-test="no-products"
        >
            {{
                summary.sent
                    ? 'Nothing to deliver.'
                    : 'You have no products to offer.'
            }}
        </p>

        <ul
            v-else
            class="pilot-panel pilot-cycle-products divide-y overflow-hidden"
        >
            <li
                v-for="product in summary.products"
                :key="product.productId"
                class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5"
                :data-test="`product-${product.productId}`"
            >
                <span class="min-w-0 font-medium break-words">{{
                    product.name
                }}</span>

                <span
                    v-if="summary.sent"
                    class="font-semibold tabular-nums"
                    data-test="quantity"
                >
                    {{ product.quantity }} {{ product.unit }}
                </span>

                <Form
                    v-else
                    v-bind="
                        ProductAvailabilityController.update.form({
                            cycle: summary.cycleId,
                            product: product.productId,
                        })
                    "
                    class="flex flex-wrap items-center gap-3"
                >
                    <input
                        type="hidden"
                        name="available"
                        :value="product.available ? 0 : 1"
                    />
                    <span
                        class="text-sm text-muted-foreground"
                        data-test="availability"
                    >
                        {{ product.available ? 'Available' : 'Unavailable' }}
                    </span>
                    <Button
                        type="submit"
                        variant="outline"
                        size="sm"
                        :data-test="`toggle-${product.productId}`"
                    >
                        {{
                            product.available
                                ? 'Mark unavailable'
                                : 'Mark available'
                        }}
                    </Button>
                </Form>
            </li>
        </ul>
    </div>
</template>
