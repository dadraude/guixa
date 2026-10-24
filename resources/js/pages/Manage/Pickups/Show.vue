<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CycleController from '@/actions/App/Http/Controllers/Manage/CycleController';
import PickupController from '@/actions/App/Http/Controllers/Manage/PickupController';
import PickupLineController from '@/actions/App/Http/Controllers/Manage/PickupLineController';
import DeliveredForm from '@/components/pickup/DeliveredForm.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';

const props = defineProps<{
    pickup: App.Http.PickupRow;
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

const euros = (cents: number) =>
    `${(cents / 100).toFixed(2).replace('.', ',')} €`;

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
    <Head :title="`Pickup ${pickup.householdName}`" />

    <div class="flex flex-col gap-8 p-4">
        <Heading
            :title="`Pickup: ${pickup.householdName}`"
            :description="`Pickup ${pickup.pickupDate}. Check what the household takes home and adjust what is needed.`"
        />

        <Link
            :href="PickupController.index.url({ cycle: pickup.cycleId })"
            class="text-sm underline"
        >
            All pickups of this cycle
        </Link>

        <InputError :message="domainError" data-test="domain-error" />

        <p
            v-if="pickup.lines.length === 0"
            class="text-sm text-muted-foreground"
            data-test="no-lines"
        >
            This pickup has no order lines.
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
                        Ordered {{ line.ordered }} {{ line.unit }} · Allocated
                        {{ line.allocated }} {{ line.unit }} ·
                        {{ euros(line.unitPriceCents) }} per {{ line.unit }}
                    </span>
                </div>
                <Badge
                    v-if="line.adjusted"
                    variant="outline"
                    data-test="adjusted"
                >
                    Adjusted
                </Badge>
                <DeliveredForm
                    v-if="pickup.status === 'pending'"
                    :line="line"
                    :processing="processing"
                    @save="adjust"
                />
                <span v-else data-test="delivered">
                    {{ line.delivered }} {{ line.unit }}
                </span>
                <span data-test="amount">{{ euros(line.amountCents) }}</span>
            </li>
        </ul>

        <dl
            v-if="
                pickup.balanceCents !== null &&
                pickup.resultingBalanceCents !== null
            "
            class="grid grid-cols-3 gap-2 text-sm"
        >
            <div>
                <dt class="text-muted-foreground">Current balance</dt>
                <dd data-test="balance">{{ euros(pickup.balanceCents) }}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Total to charge</dt>
                <dd data-test="total">{{ euros(pickup.totalCents) }}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Resulting balance</dt>
                <dd data-test="resulting-balance">
                    {{ euros(pickup.resultingBalanceCents) }}
                </dd>
            </div>
        </dl>
    </div>
</template>
