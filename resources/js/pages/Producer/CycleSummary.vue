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

    <div class="flex flex-col gap-8 p-4">
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
            class="text-sm text-muted-foreground"
            data-test="no-products"
        >
            {{
                summary.sent
                    ? 'Nothing to deliver.'
                    : 'You have no products to offer.'
            }}
        </p>

        <ul v-else class="divide-y rounded-lg border">
            <li
                v-for="product in summary.products"
                :key="product.productId"
                class="flex items-center justify-between gap-2 p-3"
                :data-test="`product-${product.productId}`"
            >
                <span class="font-medium">{{ product.name }}</span>

                <span v-if="summary.sent" data-test="quantity">
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
                    class="flex items-center gap-2"
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
