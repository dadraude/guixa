<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import OrderController from '@/actions/App/Http/Controllers/OrderController';
import { ArrowLeft } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import Adjustments from '@/components/pickup/Adjustments.vue';
import ReturnLines from '@/components/pickup/ReturnLines.vue';
import StockLines from '@/components/pickup/StockLines.vue';
import OrderPageHeader from '@/components/orders/OrderPageHeader.vue';
import OrderProductRow from '@/components/orders/OrderProductRow.vue';
import { formatMoney, formatPickupDate } from '@/lib/locale';
import InputError from '@/components/InputError.vue';
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

const cents = (amount: number) => formatMoney((amount / 100).toFixed(2));

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
    <Head :title="trans('Order')" />
    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 sm:p-8">
        <Link
            :href="OrderController.index.url()"
            class="inline-flex min-h-11 w-fit items-center gap-2 rounded-md px-2 text-sm font-medium hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
        >
            <ArrowLeft class="size-4" aria-hidden="true" />{{
                trans('Back to orders')
            }}
        </Link>
        <OrderPageHeader
            :title="trans('Your order')"
            :description="
                trans('Pickup on :date', {
                    date: formatPickupDate(order.pickupDate),
                })
            "
        />
        <InputError :message="domainError" role="alert" />
        <p
            v-if="order.cancelled"
            class="rounded-2xl border border-dashed bg-muted/50 p-6 font-medium"
            data-test="order-cancelled"
        >
            {{
                trans(
                    'This cycle was cancelled. Your orders in it were discarded.',
                )
            }}
        </p>
        <p
            v-else-if="order.producers.length === 0"
            class="rounded-2xl border border-dashed p-6 text-muted-foreground"
        >
            {{ trans('No producer takes part in this cycle yet.') }}
        </p>
        <section
            v-if="order.pickup"
            class="min-w-0 overflow-hidden rounded-2xl border bg-card text-card-foreground shadow-xs"
            data-test="pickup"
        >
            <h2 class="border-b bg-muted/40 p-4 text-lg font-semibold sm:px-5">
                {{ trans('Your pickup') }}
            </h2>
            <p
                v-if="order.pickup.status === 'nothing_to_collect'"
                class="p-4 text-muted-foreground sm:px-5"
                data-test="pickup-nothing"
            >
                {{ trans('There is nothing to collect in this pickup.') }}
            </p>
            <ul v-else class="divide-y">
                <li
                    v-for="line in order.pickup.lines"
                    :key="line.productId"
                    class="flex flex-wrap items-center justify-between gap-2 p-4 sm:px-5"
                    :data-test="`pickup-line-${line.productId}`"
                >
                    <div class="flex min-w-0 flex-col">
                        <span class="font-medium wrap-anywhere">{{
                            line.name
                        }}</span>
                        <span class="text-sm text-muted-foreground">
                            {{
                                trans(
                                    'Ordered :ordered, delivered :delivered',
                                    {
                                        ordered: line.ordered,
                                        delivered: line.delivered,
                                    },
                                )
                            }}
                            {{ line.unit }} ·
                            {{ cents(line.unitPriceCents) }}
                        </span>
                    </div>
                    <span data-test="pickup-line-amount">{{
                        cents(line.amountCents)
                    }}</span>
                </li>
            </ul>
            <div
                v-if="order.pickup.stockLines.length > 0"
                class="border-t p-4 sm:px-5"
            >
                <StockLines
                    :lines="order.pickup.stockLines"
                    :options="[]"
                    :editable="false"
                    :processing="false"
                />
            </div>
            <div
                v-if="order.pickup.returnLines.length > 0"
                class="border-t p-4 sm:px-5"
            >
                <ReturnLines
                    :lines="order.pickup.returnLines"
                    :options="[]"
                    :editable="false"
                    :processing="false"
                />
            </div>
            <div
                v-if="order.pickup.originalChargeCents != null"
                class="border-t p-4 sm:px-5"
            >
                <Adjustments
                    :lines="order.pickup.adjustments ?? []"
                    :original-charge-cents="order.pickup.originalChargeCents"
                />
            </div>
            <p class="flex justify-between border-t p-4 font-semibold sm:px-5">
                <span>{{ trans('Pickup total') }}</span>
                <span data-test="pickup-total">{{
                    cents(order.pickup.totalCents)
                }}</span>
            </p>
        </section>
        <template v-if="!order.cancelled">
            <p
                v-if="
                    groups.some((group) => group.open && group.rows.length > 0)
                "
                class="text-sm text-muted-foreground"
            >
                {{ trans('Save each product after changing its quantity.') }}
            </p>
            <section
                v-for="group in groups"
                :key="group.producerId"
                class="min-w-0 overflow-hidden rounded-2xl border bg-card text-card-foreground shadow-xs"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-b bg-muted/40 p-4 sm:px-5"
                >
                    <h2 class="min-w-0 text-lg font-semibold wrap-anywhere">
                        {{ group.producerName }}
                    </h2>
                    <span
                        class="rounded-full border px-3 py-1 text-sm font-medium"
                        :class="
                            group.open
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-background text-muted-foreground'
                        "
                    >
                        {{ group.open ? trans('Open') : trans('Closed') }}
                    </span>
                </div>
                <p
                    v-if="group.rows.length === 0"
                    class="p-5 text-muted-foreground"
                >
                    {{ trans('Nothing to order from this producer.') }}
                </p>
                <ul v-else class="divide-y">
                    <OrderProductRow
                        v-for="row in group.rows"
                        :key="row.productId"
                        :row="row"
                        :quantity="draftOf(row)"
                        :amount="
                            row.notDelivered
                                ? '0.00'
                                : lineTotal(row.price, draftOf(row))
                        "
                        :open="group.open"
                        :saving="saving === row.productId"
                        :dirty="row.productId in drafts"
                        @quantity="drafts[row.productId] = $event"
                        @save="save(row.productId)"
                        @remove="remove(row.productId)"
                    />
                </ul>
            </section>
        </template>
        <div
            class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-primary p-5 text-primary-foreground sm:p-6"
            data-test="order-total"
        >
            <span class="text-lg font-medium">{{ trans('Total') }}</span>
            <span class="text-2xl font-semibold tabular-nums">{{
                formatMoney(total)
            }}</span>
        </div>
    </div>
</template>
