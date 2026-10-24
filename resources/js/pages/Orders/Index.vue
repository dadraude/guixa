<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import OrderController from '@/actions/App/Http/Controllers/OrderController';
import { trans } from 'laravel-vue-i18n';
import CycleCard from '@/components/orders/CycleCard.vue';
import OrderPageHeader from '@/components/orders/OrderPageHeader.vue';

const props = defineProps<{
    cycles: App.Http.MemberCycleRow[];
}>();

const openCycles = computed(() => props.cycles.filter((cycle) => cycle.open));
// Until settled and closed cycles exist, a cycle that stopped taking orders waits here.
const closedCycles = computed(() =>
    props.cycles.filter((cycle) => !cycle.open && !cycle.cancelled),
);
const cancelledCycles = computed(() =>
    props.cycles.filter((cycle) => cycle.cancelled),
);

const sections = computed(() =>
    [
        { title: trans('Open for orders'), cycles: openCycles.value },
        { title: trans('Closed to orders'), cycles: closedCycles.value },
        { title: trans('Cancelled'), cycles: cancelledCycles.value },
    ].filter((section) => section.cycles.length > 0),
);
</script>

<template>
    <Head :title="trans('Orders')" />
    <div class="mx-auto flex w-full max-w-4xl flex-col gap-8 p-4 sm:p-8">
        <OrderPageHeader
            :title="trans('Orders')"
            :description="trans('The cycles your household can order in')"
        />
        <p
            v-if="cycles.length === 0"
            class="rounded-2xl border border-dashed bg-muted/40 p-8 text-center text-muted-foreground"
            data-test="no-cycles"
        >
            {{ trans('There are no cycles yet.') }}
        </p>
        <section
            v-for="section in sections"
            :key="section.title"
            class="space-y-3"
        >
            <h2 class="text-lg font-semibold">{{ section.title }}</h2>
            <ul class="grid gap-3">
                <li
                    v-for="cycle in section.cycles"
                    :key="cycle.cycleId"
                    :data-test="`cycle-${cycle.cycleId}`"
                >
                    <Link
                        :href="
                            OrderController.edit.url({ cycle: cycle.cycleId })
                        "
                        class="block rounded-2xl border bg-card text-card-foreground shadow-xs transition-colors hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring motion-reduce:transition-none"
                    >
                        <CycleCard :cycle="cycle" />
                    </Link>
                </li>
            </ul>
        </section>
    </div>
</template>
