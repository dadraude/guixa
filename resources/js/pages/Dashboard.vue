<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    Package,
    ShoppingBasket,
    Sprout,
    UsersRound,
    Wallet,
    Wheat,
    Home,
    UserCog,
    LayoutGrid,
    Timer,
    Undo2,
    Settings,
    RefreshCw,
    AlertCircle,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { trans, transChoice } from 'laravel-vue-i18n';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { formatEuroAmount } from '@/lib/formatEuroAmount';
import { formatQuantity } from '@/lib/locale';
import type { NavItem } from '@/types';
import OrderController from '@/actions/App/Http/Controllers/OrderController';
import WalletController from '@/actions/App/Http/Controllers/WalletController';
import CycleController from '@/actions/App/Http/Controllers/Manage/CycleController';
import HouseholdController from '@/actions/App/Http/Controllers/Manage/HouseholdController';
import ProducerController from '@/actions/App/Http/Controllers/Manage/ProducerController';
import StockProductController from '@/actions/App/Http/Controllers/Manage/StockProductController';
import AccountController from '@/actions/App/Http/Controllers/Manage/AccountController';
import ReturnItemController from '@/actions/App/Http/Controllers/Catalogue/ReturnItemController';
import SettingsController from '@/actions/App/Http/Controllers/Manage/SettingsController';
import ProductController from '@/actions/App/Http/Controllers/Producer/ProductController';
import CutoffController from '@/actions/App/Http/Controllers/Producer/CutoffController';

type ManagerSummary = {
    cycles: number;
    activeCycles: number;
    households: number;
    activeHouseholds: number;
    accounts: number;
    pendingInvitations: number;
    producers: number;
    activeProducers: number;
};

const props = defineProps<{
    roles: string[];
    isManager: boolean;
    isMember: boolean;
    nextPickup: string | null;
    openCycles: number;
    balance: number | null;
    attention?: App.Http.AttentionGroupRow[] | null;
    opportunities?: App.Http.OpportunityRow[] | null;
    lastUpdatedAt?: string | null;
    readFailed?: boolean;
    managerSummary: ManagerSummary | null;
    areaShortcuts?: App.Http.RoleArea[];
}>();

type Snapshot = {
    attention: App.Http.AttentionGroupRow[] | null;
    opportunities: App.Http.OpportunityRow[] | null;
    lastUpdatedAt: string | null;
};

// The last successful read: a failed read sends null sections, which must never replace it or read as "nothing pending" (R15).
const lastSuccess = ref<Snapshot | null>(null);
const saveSuccess = () => {
    if (props.readFailed || !props.lastUpdatedAt) return;
    lastSuccess.value = {
        attention: props.attention ?? null,
        opportunities: props.opportunities ?? null,
        lastUpdatedAt: props.lastUpdatedAt,
    };
};
watch(
    () => [
        props.readFailed,
        props.lastUpdatedAt,
        props.attention,
        props.opportunities,
    ],
    saveSuccess,
    { immediate: true },
);

const requestFailed = ref(false);
const busy = ref(false);
const shown = computed<Snapshot>(() =>
    props.readFailed
        ? (lastSuccess.value ?? {
              attention: null,
              opportunities: null,
              lastUpdatedAt: null,
          })
        : {
              attention: props.attention ?? null,
              opportunities: props.opportunities ?? null,
              lastUpdatedAt: props.lastUpdatedAt ?? null,
          },
);
const failed = computed(() => props.readFailed === true || requestFailed.value);
const hasFreshness = computed(
    () => shown.value.lastUpdatedAt !== null || failed.value,
);

// The source string already carries the cooperative's offset, so its wall time is the local time without a DST guess.
const readTime = computed(() => {
    const match = shown.value.lastUpdatedAt?.match(
        /^\d{4}-(\d{2})-(\d{2})T(\d{2}:\d{2})/,
    );
    return match ? `${match[2]}/${match[1]} ${match[3]}` : null;
});

function refresh() {
    if (busy.value) return;
    let succeeded = false;
    let cancelled = false;
    router.reload({
        onStart: () => {
            busy.value = true;
            requestFailed.value = false;
        },
        onSuccess: () => {
            succeeded = true;
        },
        onCancel: () => {
            cancelled = true;
        },
        onFinish: () => {
            busy.value = false;
            requestFailed.value = !succeeded && !cancelled;
        },
    });
}

// Both returns restore old props without asking the server: the browser's back/forward cache (pageshow) and Inertia's in-app history
// restore (navigate of type "history"). Each reads once (R15). A fresh visit or the initial load already has current props.
const readOnReturn = (event: PageTransitionEvent) => {
    if (event.persisted) refresh();
};
let stopListening: (() => void) | undefined;
onMounted(() => {
    window.addEventListener('pageshow', readOnReturn);
    stopListening = router.on('navigate', (event) => {
        if (event.detail.type === 'history') refresh();
    });
});
onBeforeUnmount(() => {
    window.removeEventListener('pageshow', readOnReturn);
    stopListening?.();
});

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? '');

const balanceLabel = computed(() => {
    if (props.balance === null) return null;
    const euros = (props.balance / 100).toFixed(2);
    return formatEuroAmount(euros);
});

const formattedDate = (iso: string) =>
    new Intl.DateTimeFormat('ca-ES', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    }).format(new Date(iso));

type QuickLink = { title: string; href: NavItem['href']; icon: LucideIcon };

const actionLabels: Record<Guixa.Dashboard.Domain.ActionKind, string> = {
    send_order: trans('Send producer orders'),
    check_delivery_and_allocations: trans('Check delivery and allocations'),
    resolve_allocations: trans('Resolve allocations'),
    mark_distributed: trans('Mark distributed'),
    prepare_basket: trans('Prepare baskets'),
    validate_basket: trans('Validate baskets'),
    collect_basket: trans('Collect baskets'),
};

const linkLabels: Record<Guixa.Dashboard.Domain.ActionKind, string> = {
    send_order: trans('View cycles'),
    check_delivery_and_allocations: trans('View receptions'),
    resolve_allocations: trans('View receptions'),
    mark_distributed: trans('View receptions'),
    prepare_basket: trans('View baskets'),
    validate_basket: trans('View baskets'),
    collect_basket: trans('View baskets'),
};

const dateContext = (group: App.Http.AttentionGroupRow) => {
    if (group.scheduledDate) {
        return trans('Pickup :date', {
            date: formattedDate(group.scheduledDate),
        });
    }
    return group.cycleId ? trans('Cycle') : trans('No cycle');
};

const contextLabel = (group: App.Http.AttentionGroupRow) =>
    group.producerName
        ? `${group.producerName} · ${dateContext(group)}`
        : dateContext(group);

const opportunityLabels: Record<
    Guixa.Dashboard.Domain.OpportunityKind,
    string
> = {
    member_order: trans('Ordering is open'),
    producer_summary: trans('Review your sent order'),
    catalogue_maintenance: trans('Maintain products and availability'),
};

const opportunityLinkLabels: Record<
    Guixa.Dashboard.Domain.OpportunityKind,
    string
> = {
    member_order: trans('Open the order'),
    producer_summary: trans('Open the summary'),
    catalogue_maintenance: trans('Open producers'),
};

const opportunityContext = (opportunity: App.Http.OpportunityRow) =>
    opportunity.scheduledDate
        ? `${opportunityLabels[opportunity.kind]} · ${trans('Pickup :date', { date: formattedDate(opportunity.scheduledDate) })}`
        : opportunityLabels[opportunity.kind];

const cutoffLabel = (cutoff: { producerName: string; closesAt: string }) => {
    const [date, time] = cutoff.closesAt.split(' ');
    return trans(':producer closes :date at :time', {
        producer: cutoff.producerName,
        date: formattedDate(date),
        time,
    });
};

const memberLinks: QuickLink[] = [
    {
        title: trans('Orders'),
        href: OrderController.index(),
        icon: ShoppingBasket,
    },
    { title: trans('Wallet'), href: WalletController.show(), icon: Wallet },
];

const producerLinks: QuickLink[] = [
    {
        title: trans('Products'),
        href: ProductController.index(),
        icon: Package,
    },
    {
        title: trans('Default cutoff'),
        href: CutoffController.show(),
        icon: Timer,
    },
];

const managerLinks: QuickLink[] = [
    {
        title: trans('Cycles'),
        href: CycleController.index(),
        icon: CalendarDays,
    },
    {
        title: trans('Households'),
        href: HouseholdController.index(),
        icon: Home,
    },
    {
        title: trans('Producers'),
        href: ProducerController.index(),
        icon: Wheat,
    },
    {
        title: trans('Stock'),
        href: StockProductController.index(),
        icon: Package,
    },
    {
        title: trans('Return items'),
        href: ReturnItemController.index(),
        icon: Undo2,
    },
    {
        title: trans('Accounts'),
        href: AccountController.index(),
        icon: UserCog,
    },
    {
        title: trans('Settings'),
        href: SettingsController.show(),
        icon: Settings,
    },
];

const isProducer = computed(() => props.roles.includes('producer'));
</script>

<template>
    <Head :title="trans('Dashboard')" />

    <div class="pilot-layout-page pilot-t44-page" style="max-width: 64rem">
        <header class="flex flex-col gap-2">
            <h1 class="pilot-heading text-2xl font-semibold tracking-tight">
                {{ trans('Dashboard') }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ userName }}
            </p>
            <nav
                v-if="areaShortcuts?.length"
                class="flex flex-wrap gap-2"
                :aria-label="trans('Your areas')"
            >
                <Link
                    v-for="area in areaShortcuts"
                    :key="area.role"
                    :href="area.href"
                    class="manager-button inline-flex min-h-11 items-center rounded-xl bg-[var(--secondary)] px-4 text-sm font-medium text-[var(--secondary-foreground)] transition-colors hover:bg-[var(--accent)]"
                    :data-test="`area-shortcut-${area.role}`"
                    >{{ trans(area.label) }}</Link
                >
            </nav>
        </header>

        <section
            v-if="hasFreshness"
            class="flex flex-col gap-3"
            :aria-label="trans('Last update')"
            data-test="freshness"
        >
            <Alert v-if="failed" variant="destructive" data-test="read-error">
                <AlertCircle class="size-4" aria-hidden="true" />
                <AlertDescription>{{
                    shown.lastUpdatedAt
                        ? trans(
                              'Pending work could not be updated. The last successful read is shown and may be out of date.',
                          )
                        : trans(
                              'Pending work could not be read. Try again to see it.',
                          )
                }}</AlertDescription>
            </Alert>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                <p
                    v-if="readTime"
                    class="text-sm text-muted-foreground"
                    data-test="freshness-time"
                >
                    {{ trans('Last updated :time', { time: readTime }) }}
                </p>
                <span
                    v-if="readTime && failed"
                    class="text-sm font-medium"
                    data-test="freshness-stale"
                    >{{ trans('Out of date') }}</span
                >
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-11"
                    data-test="refresh-button"
                    :aria-disabled="busy"
                    @click="refresh"
                >
                    <RefreshCw class="size-4" aria-hidden="true" />
                    {{ failed ? trans('Try again') : trans('Refresh') }}
                </Button>
            </div>
            <p
                role="status"
                class="min-h-6 text-sm text-muted-foreground"
                data-test="refresh-busy"
            >
                <template v-if="busy">{{
                    trans('Updating pending work…')
                }}</template>
            </p>
        </section>

        <section
            class="dashboard-summary grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
            :aria-label="trans('summary')"
        >
            <article v-if="isMember" class="pilot-layout-panel">
                <div class="flex items-center gap-4">
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[var(--secondary)] text-[var(--secondary-foreground)]"
                    >
                        <CalendarDays class="size-5" aria-hidden="true" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs text-muted-foreground">
                            {{ trans('Next pickup') }}
                        </p>
                        <p class="text-lg font-semibold break-words">
                            {{
                                nextPickup
                                    ? formattedDate(nextPickup)
                                    : trans('None')
                            }}
                        </p>
                    </div>
                </div>
            </article>

            <article v-if="isMember" class="pilot-layout-panel">
                <div class="flex items-center gap-4">
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[var(--secondary)] text-[var(--secondary-foreground)]"
                    >
                        <Wallet class="size-5" aria-hidden="true" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs text-muted-foreground">
                            {{ trans('Balance') }}
                        </p>
                        <p
                            class="text-lg font-semibold break-words tabular-nums"
                        >
                            {{ balanceLabel ?? '—' }}
                        </p>
                    </div>
                </div>
            </article>

            <article v-if="isMember" class="pilot-layout-panel">
                <div class="flex items-center gap-4">
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[var(--secondary)] text-[var(--secondary-foreground)]"
                    >
                        <ShoppingBasket class="size-5" aria-hidden="true" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs text-muted-foreground">
                            {{ trans('Open') }}
                        </p>
                        <p class="text-lg font-semibold break-words">
                            {{ formatQuantity(String(openCycles)) }}
                        </p>
                    </div>
                </div>
            </article>
        </section>

        <section
            v-if="shown.attention?.length"
            class="pilot-layout-panel"
            :aria-label="trans('pending work')"
            data-test="attention-list"
        >
            <div class="pilot-layout-panel-header">
                <h2 class="text-lg font-semibold">
                    {{ trans('Pending work') }}
                </h2>
            </div>
            <ul class="pilot-list">
                <li
                    v-for="group in shown.attention"
                    :key="group.key"
                    class="pilot-list-row"
                    data-test="attention-group"
                >
                    <div class="pilot-list-row-body">
                        <span class="font-medium">{{
                            actionLabels[group.action]
                        }}</span>
                        <span class="text-sm text-muted-foreground">{{
                            contextLabel(group)
                        }}</span>
                        <span
                            v-if="group.allocationDifferenceCount > 0"
                            class="text-sm"
                            data-test="attention-blocker"
                            >{{
                                trans(
                                    'Allocation differs from what was received.',
                                )
                            }}</span
                        >
                    </div>
                    <div class="flex flex-wrap items-center gap-x-4">
                        <span
                            class="text-sm tabular-nums"
                            data-test="attention-count"
                            >{{
                                transChoice(
                                    '{1} :count pending|[2,*] :count pending',
                                    group.count,
                                    {
                                        count: formatQuantity(
                                            String(group.count),
                                        ),
                                    },
                                )
                            }}</span
                        >
                        <Link
                            :href="group.href"
                            :aria-label="`${linkLabels[group.action]}: ${contextLabel(group)}`"
                            class="inline-flex min-h-11 items-center text-sm underline"
                            >{{ linkLabels[group.action] }}</Link
                        >
                    </div>
                </li>
            </ul>
        </section>

        <section
            v-if="shown.opportunities?.length"
            class="pilot-layout-panel"
            :aria-label="trans('opportunities')"
            data-test="opportunity-list"
        >
            <div class="pilot-layout-panel-header">
                <h2 class="text-lg font-semibold">
                    {{ trans('Opportunities') }}
                </h2>
            </div>
            <ul class="pilot-list">
                <li
                    v-for="opportunity in shown.opportunities"
                    :key="opportunity.key"
                    class="pilot-list-row"
                    data-test="opportunity"
                >
                    <div class="pilot-list-row-body">
                        <span class="font-medium">{{
                            opportunityLabels[opportunity.kind]
                        }}</span>
                        <span
                            v-if="opportunity.scheduledDate"
                            class="text-sm text-muted-foreground"
                            >{{
                                trans('Pickup :date', {
                                    date: formattedDate(
                                        opportunity.scheduledDate,
                                    ),
                                })
                            }}</span
                        >
                        <span
                            v-for="(cutoff, index) in opportunity.cutoffs"
                            :key="`${index}:${cutoff.producerName}`"
                            class="text-sm"
                            data-test="opportunity-cutoff"
                            >{{ cutoffLabel(cutoff) }}</span
                        >
                    </div>
                    <Link
                        :href="opportunity.href"
                        :aria-label="`${opportunityLinkLabels[opportunity.kind]}: ${opportunityContext(opportunity)}`"
                        class="inline-flex min-h-11 items-center text-sm underline"
                        >{{ opportunityLinkLabels[opportunity.kind] }}</Link
                    >
                </li>
            </ul>
        </section>

        <section
            v-if="managerSummary"
            class="pilot-layout-panel"
            :aria-label="trans('cooperative summary')"
        >
            <div class="pilot-layout-panel-header">
                <h2 class="text-lg font-semibold">
                    {{ trans('Cooperative') }}
                </h2>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <p class="text-xs text-muted-foreground">
                        {{ trans('Cycles') }}
                    </p>
                    <p class="text-2xl font-semibold tabular-nums">
                        {{ formatQuantity(String(managerSummary.cycles)) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">
                        {{ trans('Households') }}
                    </p>
                    <p class="text-2xl font-semibold tabular-nums">
                        {{ formatQuantity(String(managerSummary.households)) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">
                        {{ trans('Accounts') }}
                    </p>
                    <p class="text-2xl font-semibold tabular-nums">
                        {{ formatQuantity(String(managerSummary.accounts)) }}
                    </p>
                </div>
            </div>
        </section>

        <section
            v-if="isMember"
            class="pilot-layout-panel"
            :aria-label="trans('member actions')"
        >
            <div class="pilot-layout-panel-header">
                <h2 class="text-lg font-semibold">
                    {{ trans('Orders and wallet') }}
                </h2>
            </div>
            <div class="pilot-action-row">
                <Link
                    v-for="link in memberLinks"
                    :key="link.title"
                    :href="link.href"
                    class="manager-button inline-flex min-h-11 items-center gap-2 rounded-xl bg-[var(--secondary)] px-4 text-sm font-medium text-[var(--secondary-foreground)] transition-colors hover:bg-[var(--accent)]"
                >
                    <component
                        :is="link.icon"
                        class="size-4"
                        aria-hidden="true"
                    />
                    {{ link.title }}
                </Link>
            </div>
        </section>

        <section
            v-if="isProducer"
            class="pilot-layout-panel"
            :aria-label="trans('producer actions')"
        >
            <div class="pilot-layout-panel-header">
                <h2 class="text-lg font-semibold">
                    {{ trans('Production') }}
                </h2>
            </div>
            <div class="pilot-action-row">
                <Link
                    v-for="link in producerLinks"
                    :key="link.title"
                    :href="link.href"
                    class="manager-button inline-flex min-h-11 items-center gap-2 rounded-xl bg-[var(--secondary)] px-4 text-sm font-medium text-[var(--secondary-foreground)] transition-colors hover:bg-[var(--accent)]"
                >
                    <component
                        :is="link.icon"
                        class="size-4"
                        aria-hidden="true"
                    />
                    {{ link.title }}
                </Link>
            </div>
        </section>

        <section
            v-if="isManager"
            class="pilot-layout-panel"
            :aria-label="trans('manager actions')"
        >
            <div class="pilot-layout-panel-header">
                <h2 class="text-lg font-semibold">
                    {{ trans('Administration') }}
                </h2>
            </div>
            <div class="pilot-action-row">
                <Link
                    v-for="link in managerLinks"
                    :key="link.title"
                    :href="link.href"
                    class="manager-button inline-flex min-h-11 items-center gap-2 rounded-xl bg-[var(--secondary)] px-4 text-sm font-medium text-[var(--secondary-foreground)] transition-colors hover:bg-[var(--accent)]"
                >
                    <component
                        :is="link.icon"
                        class="size-4"
                        aria-hidden="true"
                    />
                    {{ link.title }}
                </Link>
            </div>
        </section>
    </div>
</template>
