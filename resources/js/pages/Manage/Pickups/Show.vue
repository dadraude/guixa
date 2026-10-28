<script setup lang="ts">
import { formatPickupDate, formatQuantity } from '@/lib/locale';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import CycleController from '@/actions/App/Http/Controllers/Manage/CycleController';
import PickupCompleteController from '@/actions/App/Http/Controllers/Manage/PickupCompleteController';
import PickupController from '@/actions/App/Http/Controllers/Manage/PickupController';
import PickupLineController from '@/actions/App/Http/Controllers/Manage/PickupLineController';
import PickupReturnController from '@/actions/App/Http/Controllers/Manage/PickupReturnController';
import PickupStockController from '@/actions/App/Http/Controllers/Manage/PickupStockController';
import Adjustments from '@/components/pickup/Adjustments.vue';
import NotCollectedForm from '@/components/pickup/NotCollectedForm.vue';
import CompleteForm from '@/components/pickup/CompleteForm.vue';
import DeliveredForm from '@/components/pickup/DeliveredForm.vue';
import { euros } from '@/components/pickup/euros';
import ReturnLines from '@/components/pickup/ReturnLines.vue';
import StockLines from '@/components/pickup/StockLines.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';

const props = defineProps<{
    pickup: App.Http.PickupRow;
    stockProducts: App.Http.StockLevelRow[];
    returnItems: App.Http.ReturnItemRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Cycles', href: CycleController.index() }],
    },
});

const page = usePage();
const domainError = computed(
    () => (page.props.errors as Record<string, string>).domain,
);
const errors = computed(() => page.props.errors as Record<string, string>);
const processing = ref(false);

const options = {
    preserveScroll: true,
    onStart: () => (processing.value = true),
    onFinish: () => (processing.value = false),
};

const target = () => ({
    cycle: props.pickup.cycleId,
    household: props.pickup.householdId,
});

const addStock = (stockProductId: string, quantity: string) =>
    router.post(
        PickupStockController.store.url(target()),
        { stock_product_id: stockProductId, stock_quantity: quantity },
        options,
    );

const removeStock = (stockProductId: string) =>
    router.delete(
        PickupStockController.destroy.url({
            ...target(),
            stockProduct: stockProductId,
        }),
        options,
    );

const addReturn = (returnItemId: string, quantity: string) =>
    router.post(
        PickupReturnController.store.url(target()),
        { return_item_id: returnItemId, return_quantity: quantity },
        options,
    );

const removeReturn = (returnItemId: string) =>
    router.delete(
        PickupReturnController.destroy.url({
            ...target(),
            returnItem: returnItemId,
        }),
        options,
    );

const complete = (topUp: string | null) =>
    router.post(
        PickupCompleteController.store.url(target()),
        { top_up: topUp },
        options,
    );

const markNotCollected = () =>
    router.post(
        PickupCompleteController.notCollected.url(target()),
        {},
        options,
    );

const adjust = (productId: string, quantity: string) =>
    router.put(
        PickupLineController.update.url({
            cycle: props.pickup.cycleId,
            household: props.pickup.householdId,
            product: productId,
        }),
        { quantity },
        {
            preserveScroll: true,
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
        },
    );
</script>

<template>
    <Head :title="trans('Pickup :name', { name: pickup.householdName })" />

    <div class="pilot-page pilot-layout-page pilot-manager-page">
        <Heading
            :title="trans('Pickup: :name', { name: pickup.householdName })"
            :description="
                trans(
                    'Pickup :date. Check what the household takes home and adjust what is needed.',
                    { date: formatPickupDate(pickup.pickupDate) },
                )
            "
        />

        <Link
            :href="PickupController.index.url({ cycle: pickup.cycleId })"
            class="text-sm underline"
        >
            {{ trans('All pickups of this cycle') }}
        </Link>

        <InputError :message="domainError" data-test="domain-error" />

        <p
            v-if="pickup.lines.length === 0"
            class="text-sm text-muted-foreground"
            data-test="no-lines"
        >
            {{ trans('This pickup has no order lines.') }}
        </p>

        <ul class="divide-y">
            <li
                v-for="line in pickup.lines"
                :key="line.productId"
                class="flex flex-wrap items-center justify-between gap-2 py-2"
                :data-test="`line-${line.productId}`"
            >
                <div class="flex flex-col">
                    <span>{{ line.name }}</span>
                    <span class="text-xs text-muted-foreground">
                        {{
                            trans(
                                'Ordered :ordered :unit · Allocated :allocated :unit · :price per :unit',
                                {
                                    ordered: formatQuantity(line.ordered),
                                    allocated: formatQuantity(line.allocated),
                                    unit: line.unit,
                                    price: euros(line.unitPriceCents),
                                },
                            )
                        }}
                    </span>
                </div>
                <Badge
                    v-if="line.adjusted"
                    variant="outline"
                    data-test="adjusted"
                >
                    {{ trans('Adjusted') }}
                </Badge>
                <DeliveredForm
                    v-if="pickup.status === 'pending'"
                    :line="line"
                    :processing="processing"
                    @save="adjust"
                />
                <span v-else data-test="delivered">
                    {{ formatQuantity(line.delivered) }} {{ line.unit }}
                </span>
                <span data-test="amount">{{ euros(line.amountCents) }}</span>
            </li>
        </ul>

        <StockLines
            :lines="pickup.stockLines"
            :options="stockProducts"
            :editable="pickup.status === 'pending'"
            :processing="processing"
            :errors="errors"
            @add="addStock"
            @remove="removeStock"
        />

        <ReturnLines
            :lines="pickup.returnLines"
            :options="returnItems"
            :editable="pickup.status === 'pending'"
            :processing="processing"
            :errors="errors"
            @add="addReturn"
            @remove="removeReturn"
        />

        <Adjustments
            v-if="pickup.originalChargeCents != null"
            :lines="pickup.adjustments ?? []"
            :original-charge-cents="pickup.originalChargeCents"
        />

        <dl
            v-if="
                pickup.balanceCents !== null &&
                pickup.resultingBalanceCents !== null
            "
            class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-3"
        >
            <div>
                <dt class="text-muted-foreground">
                    {{ trans('Current balance') }}
                </dt>
                <dd data-test="balance">{{ euros(pickup.balanceCents) }}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground">
                    {{
                        trans(
                            pickup.status === 'pending'
                                ? trans('Total to charge')
                                : trans('Current pickup total'),
                        )
                    }}
                </dt>
                <dd data-test="total">{{ euros(pickup.totalCents) }}</dd>
            </div>
            <div v-if="pickup.status === 'pending'">
                <dt class="text-muted-foreground">
                    {{ trans('Resulting balance') }}
                </dt>
                <dd data-test="resulting-balance">
                    {{ euros(pickup.resultingBalanceCents) }}
                </dd>
            </div>
        </dl>

        <CompleteForm
            v-if="pickup.status === 'pending'"
            :processing="processing"
            :error="errors.top_up"
            @complete="complete"
        />

        <NotCollectedForm
            v-if="pickup.status === 'pending'"
            :processing="processing"
            @submit="markNotCollected"
        />
        <p
            v-if="pickup.status === 'not_collected'"
            class="text-sm text-muted-foreground"
            data-test="not-collected"
        >
            {{ trans('This pickup was not collected and has been charged.') }}
        </p>

        <p
            v-if="pickup.status === 'completed'"
            class="text-sm text-muted-foreground"
            data-test="completed"
        >
            {{ trans('This pickup is completed.') }}
        </p>
    </div>
</template>
