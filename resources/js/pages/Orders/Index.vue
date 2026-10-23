<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import OrderController from '@/actions/App/Http/Controllers/OrderController';
import Heading from '@/components/Heading.vue';

const props = defineProps<{
    cycles: App.Http.MemberCycleRow[];
}>();

const openCycles = computed(() => props.cycles.filter((cycle) => cycle.open));
// Until cancelled, settled and closed cycles exist, a cycle that stopped taking orders waits here.
const closedCycles = computed(() =>
    props.cycles.filter((cycle) => !cycle.open),
);

const linesLabel = (count: number) =>
    count === 0 ? 'No lines yet' : `${count} ${count === 1 ? 'line' : 'lines'}`;
</script>

<template>
    <Head title="Orders" />

    <div class="flex flex-col gap-8 p-4">
        <Heading
            title="Orders"
            description="The cycles your household can order in"
        />

        <p
            v-if="cycles.length === 0"
            class="text-sm text-muted-foreground"
            data-test="no-cycles"
        >
            There are no cycles yet.
        </p>

        <section
            v-for="section in [
                { title: 'Open for orders', cycles: openCycles },
                { title: 'Closed to orders', cycles: closedCycles },
            ]"
            v-show="section.cycles.length > 0"
            :key="section.title"
            class="flex flex-col gap-3"
        >
            <h2 class="text-lg font-medium">{{ section.title }}</h2>
            <ul class="flex flex-col divide-y">
                <li
                    v-for="cycle in section.cycles"
                    :key="cycle.cycleId"
                    class="flex items-center justify-between py-2"
                    :data-test="`cycle-${cycle.cycleId}`"
                >
                    <Link
                        :href="
                            OrderController.edit.url({ cycle: cycle.cycleId })
                        "
                        class="font-medium underline"
                    >
                        Pickup on {{ cycle.pickupDate }}
                    </Link>
                    <span class="text-sm text-muted-foreground">
                        {{ linesLabel(cycle.lineCount) }}
                    </span>
                </li>
            </ul>
        </section>
    </div>
</template>
