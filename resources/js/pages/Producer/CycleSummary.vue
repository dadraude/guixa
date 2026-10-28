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

    <div class="pilot-layout-page pilot-t44-page">
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
            class="pilot-layout-notice text-sm text-muted-foreground"
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
            class="pilot-layout-panel pilot-cycle-products divide-y overflow-hidden"
        >
            <li
                v-for="product in summary.products"
                :key="product.productId"
                class="pilot-list-row flex-col sm:flex-row"
                :data-test="`product-${product.productId}`"
            >
                <span class="min-w-0 font-medium break-words">{{
                    product.name
                }}</span>

                <span
                    v-if="summary.sent"
                    class="pilot-amount font-semibold"
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
                    class="pilot-action-row"
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
