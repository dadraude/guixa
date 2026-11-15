<script setup lang="ts">
import { formatPickupDate, formatQuantity } from '@/lib/locale';
import { trans } from 'laravel-vue-i18n';
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ReceptionAllocationController from '@/actions/App/Http/Controllers/Manage/ReceptionAllocationController';
import ReceptionDistributionController from '@/actions/App/Http/Controllers/Manage/ReceptionDistributionController';
import ReceptionProductController from '@/actions/App/Http/Controllers/Manage/ReceptionProductController';
import ReceptionReceivedController from '@/actions/App/Http/Controllers/Manage/ReceptionReceivedController';
import ReceptionValidationController from '@/actions/App/Http/Controllers/Manage/ReceptionValidationController';
import ReceptionCycleController from '@/actions/App/Http/Controllers/Receptions/ReceptionCycleController';
import AddAllocationForm from '@/components/reception/AddAllocationForm.vue';
import AllocationRow from '@/components/reception/AllocationRow.vue';
import ReceivedTotalForm from '@/components/reception/ReceivedTotalForm.vue';
import FormSelect from '@/components/FormSelect.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const props = defineProps<{
    reception: App.Http.ReceptionRow;
    distribution: {
        validated: boolean;
        distributed: boolean;
        settled: boolean;
        closed?: boolean;
    };
    can: {
        edit: boolean;
        validate: boolean;
        distribute: boolean;
        reopen: boolean;
    };
    households: App.Http.HouseholdOptionRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Receptions', href: ReceptionCycleController.index() },
        ],
    },
});

const page = usePage();
const domainError = computed(
    () => (page.props.errors as Record<string, string>).domain,
);
const processing = ref(false);

const send = (url: string, data: Record<string, string | boolean>) =>
    router.put(url, data, {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
    });

const setReceived = (
    productId: string,
    received: string,
    overwriteManual: boolean,
) =>
    send(
        ReceptionReceivedController.update.url({
            cycle: props.reception.cycleId,
            producer: props.reception.producerId,
            product: productId,
        }),
        {
            received,
            overwrite_manual: overwriteManual,
        },
    );

const setAllocation = (
    productId: string,
    householdId: string,
    quantity: string,
) =>
    send(
        ReceptionAllocationController.update.url({
            cycle: props.reception.cycleId,
            producer: props.reception.producerId,
            product: productId,
            household: householdId,
        }),
        { quantity },
    );

const routeParams = computed(() => ({
    cycle: props.reception.cycleId,
    producer: props.reception.producerId,
}));
const validateReception = computed(() =>
    ReceptionValidationController.store.form(routeParams.value),
);
const distribute = computed(() =>
    ReceptionDistributionController.store.form(routeParams.value),
);
const reopen = computed(() =>
    ReceptionDistributionController.destroy.form(routeParams.value),
);
const settles = computed(() => props.can.validate || props.can.distribute);
const readyToDistribute = computed(
    () =>
        props.can.distribute &&
        props.reception.products.every(
            (product) => product.balancedForDistribution,
        ),
);

const unorderedProductOptions = computed(() =>
    props.reception.unorderedOptions.map((option) => ({
        value: option.productId,
        label: `${option.name} (${option.unit})`,
    })),
);

const addProduct = computed(() =>
    ReceptionProductController.store.form({
        cycle: props.reception.cycleId,
        producer: props.reception.producerId,
    }),
);
</script>

<template>
    <Head
        :title="
            trans('Reception :producer', { producer: reception.producerName })
        "
    />

    <div class="pilot-page pilot-layout-page pilot-manager-page">
        <Heading
            :title="
                trans('Reception: :producer', {
                    producer: reception.producerName,
                })
            "
            :description="
                trans(
                    'Pickup :date. Check what arrived and share it among the households.',
                    { date: formatPickupDate(reception.pickupDate) },
                )
            "
        />

        <InputError :message="domainError" data-test="domain-error" />

        <div
            v-if="distribution.distributed || distribution.validated || settles"
            class="flex flex-wrap items-center gap-3"
            data-test="distribution"
        >
            <Badge
                v-if="distribution.distributed"
                variant="secondary"
                class="max-w-full min-w-0 break-words whitespace-normal"
                data-test="distributed"
            >
                {{ trans('Distributed: this reception is read-only') }}
            </Badge>
            <Badge
                v-else-if="distribution.validated"
                variant="secondary"
                class="max-w-full min-w-0 break-words whitespace-normal"
                data-test="validated"
            >
                {{ trans('Validated') }}
            </Badge>

            <template v-if="settles">
                <Form
                    v-if="can.validate"
                    v-bind="validateReception"
                    :options="{ preserveScroll: true }"
                    v-slot="{ processing: validating }"
                    data-test="validate-form"
                >
                    <Button
                        type="submit"
                        variant="outline"
                        :disabled="validating"
                    >
                        {{ trans('Validate reception') }}
                    </Button>
                </Form>
                <Form
                    v-bind="distribute"
                    :options="{ preserveScroll: true }"
                    v-slot="{ processing: distributing }"
                    data-test="distribute-form"
                >
                    <Button
                        type="submit"
                        :disabled="distributing || !readyToDistribute"
                    >
                        {{ trans('Mark as distributed') }}
                    </Button>
                </Form>
                <p
                    v-if="!readyToDistribute"
                    class="text-sm text-muted-foreground"
                    data-test="distribute-hint"
                >
                    {{
                        trans(
                            'Distribute once the reception is validated and every product is balanced. Changing a received total clears the validation.',
                        )
                    }}
                </p>
            </template>
            <Form
                v-else-if="can.reopen"
                v-bind="reopen"
                :options="{ preserveScroll: true }"
                v-slot="{ processing: reopening }"
                data-test="reopen-form"
            >
                <Button type="submit" variant="outline" :disabled="reopening">
                    {{ trans('Reopen reception') }}
                </Button>
            </Form>
        </div>

        <p
            v-if="reception.products.length === 0"
            class="text-sm text-muted-foreground"
            data-test="no-products"
        >
            {{ trans('Nothing was ordered from this producer.') }}
        </p>

        <section
            v-for="product in reception.products"
            :key="product.productId"
            class="pilot-panel flex flex-col gap-3 p-3"
            :data-test="`product-${product.productId}`"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-medium">{{ product.name }}</h2>
                <Badge
                    v-if="product.balanced"
                    variant="secondary"
                    data-test="balanced"
                >
                    {{ trans('Balanced') }}
                </Badge>
                <Badge
                    v-else
                    variant="destructive"
                    class="max-w-full min-w-0 break-words whitespace-normal"
                    data-test="unbalanced"
                >
                    {{
                        trans('Unbalanced by :quantity :unit', {
                            quantity: formatQuantity(product.difference),
                            unit: product.unit,
                        })
                    }}
                </Badge>
                <Badge
                    v-if="product.hasAdjustments"
                    variant="outline"
                    class="max-w-full min-w-0 break-words whitespace-normal"
                    data-test="adjusted"
                >
                    {{
                        trans(
                            'Actual quantity differs from the allocation: this blocks nothing',
                        )
                    }}
                </Badge>
            </div>

            <dl
                class="grid grid-cols-[repeat(auto-fit,minmax(min(100%,8rem),1fr))] gap-2 text-sm"
            >
                <div>
                    <dt class="text-muted-foreground">
                        {{ trans('Ordered') }}
                    </dt>
                    <dd data-test="ordered">
                        {{ formatQuantity(product.ordered) }} {{ product.unit }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">
                        {{ trans('Received') }}
                    </dt>
                    <dd data-test="received">
                        {{ formatQuantity(product.received) }}
                        {{ product.unit }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">
                        {{ trans('Allocated') }}
                    </dt>
                    <dd data-test="allocated">
                        {{ formatQuantity(product.allocated) }}
                        {{ product.unit }}
                    </dd>
                </div>
                <div v-if="product.hasAdjustments">
                    <dt class="text-muted-foreground">
                        {{ trans('Actual') }}
                    </dt>
                    <dd data-test="delivered">
                        {{ formatQuantity(product.delivered) }}
                        {{ product.unit }}
                    </dd>
                </div>
            </dl>

            <Form
                v-if="can.edit && product.nobodyOrdered"
                v-bind="addProduct"
                :options="{ preserveScroll: true }"
                class="flex flex-wrap items-end gap-2"
                :data-test="`unordered-total-${product.productId}`"
                v-slot="{ processing: saving }"
            >
                <input
                    type="hidden"
                    name="product_id"
                    :value="product.productId"
                />
                <div class="grid max-w-full min-w-0 grid-cols-1 gap-1">
                    <label
                        class="text-sm"
                        :for="`unordered-received-${product.productId}`"
                    >
                        {{
                            trans(
                                'Nobody ordered this product, so the received total is not shared out: allocate it by hand below.',
                            )
                        }}
                    </label>
                    <Input
                        :id="`unordered-received-${product.productId}`"
                        name="received"
                        inputmode="decimal"
                        :default-value="product.received"
                        class="w-32 max-w-full"
                    />
                </div>
                <Button
                    type="submit"
                    size="sm"
                    class="h-auto min-h-11 max-w-full whitespace-normal"
                    :disabled="saving"
                >
                    {{ trans('Set received total') }}
                </Button>
            </Form>
            <ReceivedTotalForm
                v-else-if="can.edit"
                :product="product"
                :processing="processing"
                @save="
                    (received, overwriteManual) =>
                        setReceived(
                            product.productId,
                            received,
                            overwriteManual,
                        )
                "
            />

            <ul class="divide-y">
                <AllocationRow
                    v-for="allocation in product.allocations"
                    :key="allocation.householdId"
                    :product-id="product.productId"
                    :unit="product.unit"
                    :allocation="allocation"
                    :processing="processing"
                    :readonly="!can.edit"
                    @save="
                        (householdId, quantity) =>
                            setAllocation(
                                product.productId,
                                householdId,
                                quantity,
                            )
                    "
                />
            </ul>

            <AddAllocationForm
                v-if="can.edit"
                :product="product"
                :households="households"
                :processing="processing"
                @allocate="
                    (householdId, quantity) =>
                        setAllocation(product.productId, householdId, quantity)
                "
            />
        </section>

        <Form
            v-if="can.edit && reception.unorderedOptions.length > 0"
            v-bind="addProduct"
            :options="{ preserveScroll: true }"
            class="flex flex-wrap items-end gap-2"
            data-test="add-product"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-1">
                <label class="text-sm font-medium" for="unordered-product">
                    {{ trans('Add a product nobody ordered') }}
                </label>
                <FormSelect
                    id="unordered-product"
                    name="product_id"
                    required
                    :options="unorderedProductOptions"
                    :default-value="unorderedProductOptions[0]?.value"
                    class="w-full min-w-0 sm:w-fit sm:min-w-44"
                />
            </div>
            <Input
                name="received"
                inputmode="decimal"
                :placeholder="trans('Received')"
                :aria-label="trans('Received total')"
                class="w-28"
            />
            <Button type="submit" :disabled="processing">{{
                trans('Add product')
            }}</Button>
            <InputError :message="errors.product_id" />
        </Form>
    </div>
</template>
