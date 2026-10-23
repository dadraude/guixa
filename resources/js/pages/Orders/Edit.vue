<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import OrderController from '@/actions/App/Http/Controllers/OrderController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { orderRows, type OrderRow } from '@/lib/orderRows';
import { lineCents, orderTotal } from '@/lib/orderTotal';

const props = defineProps<{
    order: App.Http.OrderRow;
}>();

const page = usePage();
const domainError = computed(
    () => (page.props.errors as Record<string, string>).domain,
);

const groups = computed(() => orderRows(props.order));

// What each box holds, saved or not; the total follows it.
const drafts = reactive<Record<string, string>>({});
const draftOf = (row: OrderRow) =>
    drafts[row.productId] ?? (row.quantity || '0');

const total = computed(() =>
    orderTotal(
        groups.value.flatMap((group) =>
            group.rows.map((row) => ({
                price: row.price,
                quantity: row.notDelivered ? '0' : draftOf(row),
            })),
        ),
    ),
);

const saving = ref<string | null>(null);

function save(productId: string) {
    router.put(
        OrderController.updateLine.url({
            cycle: props.order.cycleId,
            product: productId,
        }),
        { quantity: drafts[productId] },
        {
            preserveScroll: true,
            only: ['order', 'errors'],
            onStart: () => (saving.value = productId),
            onFinish: () => (saving.value = null),
            onSuccess: () => delete drafts[productId],
        },
    );
}

function remove(productId: string) {
    router.delete(
        OrderController.removeMarkedLine.url({
            cycle: props.order.cycleId,
            product: productId,
        }),
        {
            preserveScroll: true,
            only: ['order', 'errors'],
            onStart: () => (saving.value = productId),
            onFinish: () => (saving.value = null),
        },
    );
}

const lineTotal = (price: string, quantity: string) =>
    (lineCents({ price, quantity }) / 100).toFixed(2);
</script>

<template>
    <Head title="Order" />

    <div class="flex flex-col gap-8 p-4">
        <Heading
            title="Your order"
            :description="`Pickup on ${order.pickupDate}`"
        />

        <InputError :message="domainError" />

        <p
            v-if="order.cancelled"
            class="text-sm font-medium"
            data-test="order-cancelled"
        >
            This cycle was cancelled. Your orders in it were discarded.
        </p>

        <p
            v-else-if="order.producers.length === 0"
            class="text-sm text-muted-foreground"
        >
            No producer takes part in this cycle yet.
        </p>

        <section
            v-for="group in order.cancelled ? [] : groups"
            :key="group.producerId"
            class="flex flex-col gap-3"
        >
            <div class="flex items-center gap-3">
                <h2 class="text-lg font-medium">
                    {{ group.producerName }}
                </h2>
                <span
                    class="text-sm"
                    :class="
                        group.open ? 'text-green-700' : 'text-muted-foreground'
                    "
                >
                    {{ group.open ? 'Open' : 'Closed' }}
                </span>
            </div>

            <p
                v-if="group.rows.length === 0"
                class="text-sm text-muted-foreground"
            >
                Nothing to order from this producer.
            </p>

            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left">
                        <th class="py-2">Product</th>
                        <th class="py-2">Price</th>
                        <th class="py-2">Quantity</th>
                        <th class="py-2 text-right">Amount</th>
                        <th class="py-2" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in group.rows"
                        :key="row.productId"
                        class="border-b"
                    >
                        <td class="py-2">
                            {{ row.name }}
                            <span
                                v-if="row.notDelivered"
                                class="ml-2 text-xs text-destructive"
                                :data-test="`not-delivered-${row.productId}`"
                            >
                                Will not be delivered
                            </span>
                        </td>
                        <td class="py-2">
                            {{ row.price }} EUR / {{ row.unit }}
                        </td>
                        <td class="py-2">
                            <Input
                                :model-value="draftOf(row)"
                                :disabled="!group.open || row.notDelivered"
                                :inputmode="
                                    row.variable ? 'decimal' : 'numeric'
                                "
                                class="w-28"
                                :data-test="`quantity-${row.productId}`"
                                @update:model-value="
                                    drafts[row.productId] = String($event)
                                "
                                @keydown.enter="save(row.productId)"
                            />
                        </td>
                        <td class="py-2 text-right">
                            {{
                                row.notDelivered
                                    ? '0.00'
                                    : lineTotal(row.price, draftOf(row))
                            }}
                            EUR
                        </td>
                        <td class="flex justify-end py-2">
                            <Button
                                v-if="row.notDelivered"
                                size="sm"
                                variant="outline"
                                :disabled="saving === row.productId"
                                :data-test="`remove-${row.productId}`"
                                @click="remove(row.productId)"
                            >
                                Remove
                            </Button>
                            <Button
                                v-else
                                size="sm"
                                variant="outline"
                                :disabled="
                                    !group.open ||
                                    saving === row.productId ||
                                    !(row.productId in drafts)
                                "
                                :data-test="`save-${row.productId}`"
                                @click="save(row.productId)"
                            >
                                Save
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <p class="text-lg font-medium" data-test="order-total">
            Total: {{ total }} EUR
        </p>
    </div>
</template>
