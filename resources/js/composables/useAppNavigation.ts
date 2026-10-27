import { usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    Home,
    LayoutGrid,
    Package,
    Settings,
    Timer,
    Undo2,
    UserCog,
    Wallet,
    Wheat,
} from '@lucide/vue';
import { computed } from 'vue';
import OrderController from '@/actions/App/Http/Controllers/OrderController';
import AccountController from '@/actions/App/Http/Controllers/Manage/AccountController';
import CycleController from '@/actions/App/Http/Controllers/Manage/CycleController';
import HouseholdController from '@/actions/App/Http/Controllers/Manage/HouseholdController';
import ProducerController from '@/actions/App/Http/Controllers/Manage/ProducerController';
import ReturnItemController from '@/actions/App/Http/Controllers/Manage/ReturnItemController';
import SettingsController from '@/actions/App/Http/Controllers/Manage/SettingsController';
import StockProductController from '@/actions/App/Http/Controllers/Manage/StockProductController';
import CutoffController from '@/actions/App/Http/Controllers/Producer/CutoffController';
import ProductController from '@/actions/App/Http/Controllers/Producer/ProductController';
import WalletController from '@/actions/App/Http/Controllers/WalletController';
import { toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const managerItems: NavItem[] = [
    { title: 'Cycles', href: CycleController.index(), icon: CalendarDays },
    { title: 'Households', href: HouseholdController.index(), icon: Home },
    { title: 'Accounts', href: AccountController.index(), icon: UserCog },
    { title: 'Producers', href: ProducerController.index(), icon: Wheat },
    { title: 'Stock', href: StockProductController.index(), icon: Package },
    { title: 'Return items', href: ReturnItemController.index(), icon: Undo2 },
    { title: 'Settings', href: SettingsController.show(), icon: Settings },
];

const producerItems: NavItem[] = [
    { title: 'Products', href: ProductController.index(), icon: Package },
    { title: 'Default cutoff', href: CutoffController.show(), icon: Timer },
];

const memberItems: NavItem[] = [
    { title: 'Orders', href: OrderController.index(), icon: CalendarDays },
    { title: 'Wallet', href: WalletController.show(), icon: Wallet },
];

export function navigationItemsFor(
    areas: readonly { role: string }[],
): NavItem[] {
    const roles = new Set(areas.map((area) => area.role));

    return [
        { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
        ...(roles.has('member') ? memberItems : []),
        ...(roles.has('manager') ? managerItems : []),
        ...(roles.has('producer') ? producerItems : []),
    ];
}

function pathOf(url: string): string {
    return new URL(url, 'http://localhost').pathname.replace(/\/$/, '') || '/';
}

export function currentNavigationItem(
    items: readonly NavItem[],
    currentUrl: string,
): NavItem | undefined {
    const currentPath = pathOf(currentUrl);

    return [...items]
        .filter((item) => {
            const destination = pathOf(toUrl(item.href));

            return (
                currentPath === destination ||
                currentPath.startsWith(`${destination}/`)
            );
        })
        .sort(
            (left, right) =>
                pathOf(toUrl(right.href)).length -
                pathOf(toUrl(left.href)).length,
        )[0];
}

export function useAppNavigation() {
    const page = usePage();
    const areas = computed(() => page.props.roleAreas ?? []);
    const items = computed(() => navigationItemsFor(areas.value));
    const currentItem = computed(() =>
        currentNavigationItem(items.value, page.url),
    );

    return { areas, items, currentItem };
}
