<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CycleController from '@/actions/App/Http/Controllers/Manage/CycleController';
import ReceptionAllocationController from '@/actions/App/Http/Controllers/Manage/ReceptionAllocationController';
import ReceptionDistributionController from '@/actions/App/Http/Controllers/Manage/ReceptionDistributionController';
import ReceptionProductController from '@/actions/App/Http/Controllers/Manage/ReceptionProductController';
import ReceptionReceivedController from '@/actions/App/Http/Controllers/Manage/ReceptionReceivedController';
import ReceptionValidationController from '@/actions/App/Http/Controllers/Manage/ReceptionValidationController';
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
    households: App.Http.HouseholdOptionRow[];
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
    includeCompleted?: boolean,
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
            ...(includeCompleted === undefined
                ? {}
                : { include_completed: includeCompleted }),
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
const canDistribute = computed(
    () =>
        props.distribution.validated &&
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
    <Head :title="`Reception ${reception.producerName}`" />

    <div class="flex flex-col gap-8 p-4">
        <Heading
            :title="`Reception: ${reception.producerName}`"
            :description="`Pickup ${reception.pickupDate}. Check what arrived and share it among the households.`"
        />

        <InputError :message="domainError" data-test="domain-error" />

        <div class="flex flex-wrap items-center gap-3" data-test="distribution">
            <Badge
                v-if="distribution.distributed"
                variant="secondary"
                data-test="distributed"
            >
                Distributed: this reception is read-only
            </Badge>
            <Badge
                v-else-if="distribution.validated"
                variant="secondary"
                data-test="validated"
            >
                Validated
            </Badge>

            <template v-if="!distribution.distributed">
                <Form
                    v-if="!distribution.validated"
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
                        Validate reception
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
                        :disabled="distributing || !canDistribute"
                    >
                        Mark as distributed
                    </Button>
                </Form>
                <p
                    v-if="!canDistribute"
                    class="text-sm text-muted-foreground"
                    data-test="distribute-hint"
                >
                    Distribute once the reception is validated and every product
                    is balanced. Changing a received total clears the
                    validation.
                </p>
            </template>
            <Form
                v-else-if="!distribution.closed"
                v-bind="reopen"
                :options="{ preserveScroll: true }"
                v-slot="{ processing: reopening }"
                data-test="reopen-form"
            >
                <Button type="submit" variant="outline" :disabled="reopening">
                    Reopen reception
                </Button>
            </Form>
        </div>

        <p
            v-if="reception.products.length === 0"
            class="text-sm text-muted-foreground"
            data-test="no-products"
        >
            Nothing was ordered from this producer.
        </p>

        <section
            v-for="product in reception.products"
            :key="product.productId"
            class="flex flex-col gap-3 rounded-lg border p-3"
            :data-test="`product-${product.productId}`"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-medium">{{ product.name }}</h2>
                <Badge
                    v-if="product.balanced"
                    variant="secondary"
                    data-test="balanced"
                >
                    Balanced
                </Badge>
                <Badge v-else variant="destructive" data-test="unbalanced">
                    Unbalanced by {{ product.difference }} {{ product.unit }}
                </Badge>
                <Badge
                    v-if="product.hasAdjustments"
                    variant="outline"
                    data-test="adjusted"
                >
                    Adjusted at pickup: this blocks nothing
                </Badge>
            </div>

            <dl class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
                <div>
                    <dt class="text-muted-foreground">Ordered</dt>
                    <dd data-test="ordered">
                        {{ product.ordered }} {{ product.unit }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Received</dt>
                    <dd data-test="received">
                        {{ product.received }} {{ product.unit }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Allocated</dt>
                    <dd data-test="allocated">
                        {{ product.allocated }} {{ product.unit }}
                    </dd>
                </div>
                <div v-if="product.hasAdjustments">
                    <dt class="text-muted-foreground">Delivered</dt>
                    <dd data-test="delivered">
                        {{ product.delivered }} {{ product.unit }}
                    </dd>
                </div>
            </dl>

            <Form
                v-if="!distribution.distributed && product.nobodyOrdered"
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
                <div class="grid gap-1">
                    <label
                        class="text-sm"
                        :for="`unordered-received-${product.productId}`"
                    >
                        Nobody ordered this product, so the received total is
                        not shared out: allocate it by hand below.
                    </label>
                    <Input
                        :id="`unordered-received-${product.productId}`"
                        name="received"
                        inputmode="decimal"
                        :default-value="product.received"
                        class="w-32"
                    />
                </div>
                <Button type="submit" size="sm" :disabled="saving">
                    Set received total
                </Button>
            </Form>
            <ReceivedTotalForm
                v-else-if="!distribution.distributed"
                :product="product"
                :processing="processing"
                @save="
                    (received, overwriteManual, includeCompleted) =>
                        setReceived(
                            product.productId,
                            received,
                            overwriteManual,
                            includeCompleted,
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
                    :readonly="distribution.distributed"
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
                v-if="!distribution.distributed"
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
            v-if="
                !distribution.distributed &&
                reception.unorderedOptions.length > 0
            "
            v-bind="addProduct"
            :options="{ preserveScroll: true }"
            class="flex flex-wrap items-end gap-2"
            data-test="add-product"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-1">
                <label class="text-sm font-medium" for="unordered-product">
                    Add a product nobody ordered
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
                placeholder="Received"
                aria-label="Received total"
                class="w-28"
            />
            <Button type="submit" :disabled="processing">Add product</Button>
            <InputError :message="errors.product_id" />
        </Form>
    </div>
</template>
