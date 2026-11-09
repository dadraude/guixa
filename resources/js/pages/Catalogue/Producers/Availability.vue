<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import ProducerAvailabilityController from '@/actions/App/Http/Controllers/Catalogue/ProducerAvailabilityController';
import ProducerController from '@/actions/App/Http/Controllers/Catalogue/ProducerController';
import Heading from '@/components/Heading.vue';
import StatusIndicator from '@/components/StatusIndicator.vue';
import { Button } from '@/components/ui/button';
import { formatPickupDate } from '@/lib/locale';

type CycleAvailability = {
    cycleId: string;
    pickupDate: string;
    canChange: boolean;
    products: {
        productId: string;
        name: string;
        unit: string;
        available: boolean;
    }[];
};

defineProps<{
    producer: { id: string; name: string; active: boolean };
    cycles: CycleAvailability[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Producers', href: ProducerController.index() }],
    },
});
</script>

<template>
    <Head :title="trans('Availability of :name', { name: producer.name })" />
    <div class="pilot-page pilot-layout-page pilot-manager-page">
        <Heading
            :title="trans('Availability of :name', { name: producer.name })"
            :description="
                trans(
                    'Mark a product unavailable in a cycle until the producer order is sent',
                )
            "
        />
        <StatusIndicator
            v-if="!producer.active"
            :label="trans('Deactivated')"
            tone="danger"
            data-test="producer-deactivated"
        />
        <p
            v-if="!producer.active"
            class="pilot-layout-notice text-sm text-muted-foreground"
        >
            {{ trans('A deactivated producer cannot be ordered from.') }}
        </p>
        <p
            v-if="cycles.length === 0"
            class="pilot-layout-notice text-sm text-muted-foreground"
            data-test="no-cycles"
        >
            {{ trans('No cycles are open for this producer.') }}
        </p>
        <section
            v-for="cycle in cycles"
            :key="cycle.cycleId"
            class="space-y-2"
            :data-test="`cycle-${cycle.cycleId}`"
        >
            <h2 class="font-semibold">
                {{
                    trans('Pickup :date', {
                        date: formatPickupDate(cycle.pickupDate),
                    })
                }}
            </h2>
            <p
                v-if="cycle.products.length === 0"
                class="pilot-layout-notice text-sm text-muted-foreground"
            >
                {{ trans('This producer has no active weekly products.') }}
            </p>
            <ul
                v-else
                class="pilot-layout-panel pilot-cycle-products divide-y overflow-hidden"
            >
                <li
                    v-for="product in cycle.products"
                    :key="product.productId"
                    class="pilot-list-row flex-col items-stretch sm:flex-row sm:items-center"
                    :data-test="`product-${product.productId}`"
                >
                    <span class="min-w-0 font-medium wrap-anywhere">{{
                        product.name
                    }}</span>
                    <span class="pilot-action-row max-w-full min-w-0">
                        <span
                            class="text-sm text-muted-foreground"
                            data-test="availability"
                        >
                            {{
                                product.available
                                    ? trans('Available')
                                    : trans('Unavailable')
                            }}
                        </span>
                        <Form
                            v-if="cycle.canChange"
                            class="max-w-full min-w-0"
                            v-bind="
                                ProducerAvailabilityController.update.form({
                                    producer: producer.id,
                                    cycle: cycle.cycleId,
                                    product: product.productId,
                                })
                            "
                        >
                            <input
                                type="hidden"
                                name="available"
                                :value="product.available ? 0 : 1"
                            />
                            <Button
                                type="submit"
                                variant="outline"
                                size="sm"
                                class="h-auto min-h-11 max-w-full min-w-11 py-2 text-center break-words whitespace-normal"
                                :data-test="`toggle-${product.productId}`"
                                :aria-label="`${product.available ? trans('Mark unavailable') : trans('Mark available')}: ${product.name}`"
                            >
                                {{
                                    product.available
                                        ? trans('Mark unavailable')
                                        : trans('Mark available')
                                }}
                            </Button>
                        </Form>
                    </span>
                </li>
            </ul>
        </section>
    </div>
</template>
