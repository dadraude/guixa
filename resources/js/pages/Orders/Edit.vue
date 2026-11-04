<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import MemberBasketController from '@/actions/App/Http/Controllers/Member/BasketController';
import OrderController from '@/actions/App/Http/Controllers/OrderController';
import { ArrowLeft } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import OrderPageHeader from '@/components/orders/OrderPageHeader.vue';
import OrderProductRow from '@/components/orders/OrderProductRow.vue';
import { euros } from '@/components/pickup/euros';
import { formatPickupDate } from '@/lib/locale';
import InputError from '@/components/InputError.vue';
import { orderRows, type OrderRow } from '@/lib/orderRows';

const props = defineProps<{
    order: App.Http.OrderRow;
    /** What the quantities typed so far cost, priced by the server. */
    preview: App.Http.OrderPreviewRow;
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

// The server prices what is typed: a short pause after the last keystroke asks for a new preview.
const PREVIEW_DELAY_MS = 250;
let previewTimer: ReturnType<typeof setTimeout> | undefined;

function refreshPreview() {
    router.get(
        OrderController.edit.url({ cycle: props.order.cycleId }),
        { typed: { ...drafts } },
        {
            only: ['preview'],
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

watch(drafts, () => {
    clearTimeout(previewTimer);
    previewTimer = setTimeout(refreshPreview, PREVIEW_DELAY_MS);
});
onBeforeUnmount(() => clearTimeout(previewTimer));

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
</script>

<template>
    <Head :title="trans('Order')" />
    <div class="pilot-layout-page mx-auto max-w-5xl">
        <Link
            :href="OrderController.index.url()"
            class="-ml-2 inline-flex min-h-11 w-fit items-center gap-2 rounded-md px-2 text-sm font-medium hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
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
        <InputError :message="domainError" role="alert" class="pilot-error" />
        <p
            v-if="order.cancelled"
            class="pilot-layout-notice font-medium"
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
            class="pilot-layout-notice text-muted-foreground"
        >
            {{ trans('No producer takes part in this cycle yet.') }}
        </p>
        <section
            v-if="order.basketId"
            class="pilot-layout-panel"
            data-test="pickup"
        >
            <div class="pilot-layout-panel-header">
                <h2 class="pilot-heading text-lg font-semibold">
                    {{ trans('Your basket') }}
                </h2>
            </div>
            <p class="text-muted-foreground">
                {{
                    trans(
                        'This cycle is settled. See what was ordered and what you take in your basket.',
                    )
                }}
            </p>
            <Link
                :href="
                    MemberBasketController.show.url({ basket: order.basketId })
                "
                class="inline-flex min-h-11 items-center text-sm underline"
                data-test="basket-link"
            >
                {{ trans('Open your basket') }}
            </Link>
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
                class="pilot-layout-panel"
            >
                <div class="pilot-layout-panel-header">
                    <h2
                        class="pilot-heading min-w-0 text-lg font-semibold wrap-anywhere"
                    >
                        {{ group.producerName }}
                    </h2>
                    <span
                        class="rounded-full border px-2 py-1 text-sm font-medium"
                        :class="
                            group.open
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-background text-muted-foreground'
                        "
                    >
                        {{ group.open ? trans('Open') : trans('Closed') }}
                    </span>
                </div>
                <p v-if="group.rows.length === 0" class="text-muted-foreground">
                    {{ trans('Nothing to order from this producer.') }}
                </p>
                <ul v-else class="pilot-list">
                    <OrderProductRow
                        v-for="row in group.rows"
                        :key="row.productId"
                        :row="row"
                        :quantity="draftOf(row)"
                        :amount-cents="preview.amountCents[row.productId] ?? 0"
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
            class="pilot-layout-panel pilot-layout-total pilot-total-row"
            data-test="order-total"
        >
            <span class="text-lg font-medium">{{ trans('Total') }}</span>
            <span class="pilot-amount text-2xl font-semibold">{{
                euros(preview.totalCents)
            }}</span>
        </div>
    </div>
</template>
