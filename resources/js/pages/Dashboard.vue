<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
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
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { formatEuroAmount } from '@/lib/formatEuroAmount';
import { formatQuantity } from '@/lib/locale';
import type { NavItem } from '@/types';
import MemberBasketController from '@/actions/App/Http/Controllers/Member/BasketController';
import FinanceBasketController from '@/actions/App/Http/Controllers/Finance/BasketController';
import OrderController from '@/actions/App/Http/Controllers/OrderController';
import WalletController from '@/actions/App/Http/Controllers/WalletController';
import CycleController from '@/actions/App/Http/Controllers/Manage/CycleController';
import HouseholdController from '@/actions/App/Http/Controllers/Manage/HouseholdController';
import ProducerController from '@/actions/App/Http/Controllers/Manage/ProducerController';
import StockProductController from '@/actions/App/Http/Controllers/Manage/StockProductController';
import AccountController from '@/actions/App/Http/Controllers/Manage/AccountController';
import ReturnItemController from '@/actions/App/Http/Controllers/Manage/ReturnItemController';
import SettingsController from '@/actions/App/Http/Controllers/Manage/SettingsController';
import ProductController from '@/actions/App/Http/Controllers/Producer/ProductController';
import CutoffController from '@/actions/App/Http/Controllers/Producer/CutoffController';

type PendingPickup = {
    basketId: string;
    validatedAt: string | null;
    weekly: boolean;
    late: boolean;
};

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
    pendingPickups: PendingPickup[];
    staffPendingCollections?: { count: number } | null;
    managerSummary: ManagerSummary | null;
}>();

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
        </header>

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
                        <p class="truncate text-lg font-semibold">
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
                        <p class="truncate text-lg font-semibold tabular-nums">
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
                        <p class="truncate text-lg font-semibold">
                            {{ formatQuantity(String(openCycles)) }}
                        </p>
                    </div>
                </div>
            </article>
        </section>

        <section
            v-if="pendingPickups.length > 0"
            class="pilot-layout-panel"
            :aria-label="trans('pending pickups')"
        >
            <div class="pilot-layout-panel-header">
                <h2 class="text-lg font-semibold">
                    {{ trans('Pending pickups') }}
                </h2>
            </div>
            <ul class="pilot-list" data-test="pending-pickups">
                <li
                    v-for="p in pendingPickups"
                    :key="p.basketId"
                    class="pilot-list-row"
                >
                    <Link
                        :href="
                            MemberBasketController.show.url({
                                basket: p.basketId,
                            })
                        "
                        class="inline-flex min-h-11 items-center text-sm underline"
                        >{{
                            p.validatedAt
                                ? formattedDate(p.validatedAt)
                                : trans('Pending collection')
                        }}</Link
                    >
                    <span v-if="p.late" class="text-xs text-muted-foreground">{{
                        trans('Late basket')
                    }}</span>
                </li>
            </ul>
            <Link
                :href="
                    MemberBasketController.index.url({ query: { pending: 1 } })
                "
                class="inline-flex min-h-11 items-center text-sm underline"
                data-test="pending-pickups-all"
                >{{ trans('All pending collections') }}</Link
            >
        </section>

        <section
            v-if="staffPendingCollections && staffPendingCollections.count > 0"
            class="pilot-layout-panel"
            :aria-label="trans('pending collections')"
        >
            <div class="pilot-layout-panel-header">
                <h2 class="text-lg font-semibold">
                    {{ trans('Pending collections') }}
                </h2>
            </div>
            <Link
                :href="
                    FinanceBasketController.index.url({
                        query: { pending: 1 },
                    })
                "
                class="inline-flex min-h-11 items-center text-sm underline"
                data-test="staff-pending-collections"
                >{{
                    trans('Pending collections: :count', {
                        count: String(staffPendingCollections.count),
                    })
                }}</Link
            >
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
