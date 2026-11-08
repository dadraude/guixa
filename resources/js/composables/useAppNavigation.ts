import { usePage } from '@inertiajs/vue3';
import {
    Boxes,
    CalendarDays,
    Home,
    LayoutGrid,
    Package,
    Settings,
    Settings2,
    ShieldCheck,
    ShoppingBasket,
    Sprout,
    Timer,
    Undo2,
    UserCog,
    UsersRound,
    Wallet,
    Wheat,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { computed } from 'vue';
import OrderController from '@/actions/App/Http/Controllers/OrderController';
import StockController from '@/actions/App/Http/Controllers/StockController';
import AccountController from '@/actions/App/Http/Controllers/Manage/AccountController';
import CycleController from '@/actions/App/Http/Controllers/Manage/CycleController';
import HouseholdController from '@/actions/App/Http/Controllers/Manage/HouseholdController';
import ProducerController from '@/actions/App/Http/Controllers/Manage/ProducerController';
import ReturnItemController from '@/actions/App/Http/Controllers/Manage/ReturnItemController';
import RoleController from '@/actions/App/Http/Controllers/Manage/RoleController';
import SettingsController from '@/actions/App/Http/Controllers/Manage/SettingsController';
import StockProductController from '@/actions/App/Http/Controllers/Manage/StockProductController';
import CutoffController from '@/actions/App/Http/Controllers/Producer/CutoffController';
import ProductController from '@/actions/App/Http/Controllers/Producer/ProductController';
import WalletController from '@/actions/App/Http/Controllers/WalletController';
import BasketController from '@/actions/App/Http/Controllers/Finance/BasketController';
import FinanceStockController from '@/actions/App/Http/Controllers/Finance/StockController';
import MemberBasketController from '@/actions/App/Http/Controllers/Member/BasketController';
import { toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

export type NavigationSection = {
    title: string;
    icon: LucideIcon;
    items: NavItem[];
};

const producerItems: NavItem[] = [
    { title: 'Products', href: ProductController.index(), icon: Package },
    { title: 'Default cutoff', href: CutoffController.show(), icon: Timer },
];

const memberItems: NavItem[] = [
    { title: 'Orders', href: OrderController.index(), icon: CalendarDays },
    {
        title: 'My baskets',
        href: MemberBasketController.index(),
        icon: ShoppingBasket,
    },
    { title: 'Stock catalogue', href: StockController.index(), icon: Package },
    { title: 'Wallet', href: WalletController.show(), icon: Wallet },
];

export function navigationSectionsFor(
    areas: readonly { role: string }[],
): NavigationSection[] {
    const roles = new Set(areas.map((area) => area.role));

    return [
        {
            title: 'Overview',
            icon: LayoutGrid,
            items: [
                { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
            ],
        },
        ...(roles.has('member')
            ? [
                  {
                      title: 'Orders and wallet',
                      icon: ShoppingBasket,
                      items: memberItems,
                  },
              ]
            : []),
        ...(roles.has('finance') && !roles.has('manager')
            ? [
                  {
                      title: 'Finance',
                      icon: Wallet,
                      items: [
                          {
                              title: 'Baskets',
                              href: BasketController.index(),
                              icon: ShoppingBasket,
                          },
                          {
                              title: 'Stock',
                              href: FinanceStockController.index(),
                              icon: Package,
                              alsoActiveOn: ['/finance/stock'],
                          },
                      ],
                  },
              ]
            : []),
        ...(roles.has('manager')
            ? [
                  {
                      title: 'Cooperative',
                      icon: UsersRound,
                      items: [
                          {
                              title: 'Cycles',
                              href: CycleController.index(),
                              icon: CalendarDays,
                          },
                          {
                              title: 'Households',
                              href: HouseholdController.index(),
                              icon: Home,
                              alsoActiveOn: ['/finance/households'],
                          },
                          {
                              title: 'Baskets',
                              href: BasketController.index(),
                              icon: ShoppingBasket,
                          },
                      ],
                  },
                  {
                      title: 'Catalog and stock',
                      icon: Boxes,
                      items: [
                          {
                              title: 'Producers',
                              href: ProducerController.index(),
                              icon: Wheat,
                          },
                          {
                              title: 'Stock',
                              href: StockProductController.index(),
                              icon: Package,
                          },
                          {
                              title: 'Return items',
                              href: ReturnItemController.index(),
                              icon: Undo2,
                          },
                      ],
                  },
                  {
                      title: 'Administration',
                      icon: Settings2,
                      items: [
                          {
                              title: 'Accounts',
                              href: AccountController.index(),
                              icon: UserCog,
                          },
                          {
                              title: 'Roles',
                              href: RoleController.index(),
                              icon: ShieldCheck,
                          },
                          {
                              title: 'Settings',
                              href: SettingsController.show(),
                              icon: Settings,
                          },
                      ],
                  },
              ]
            : []),
        ...(roles.has('producer')
            ? [{ title: 'Production', icon: Sprout, items: producerItems }]
            : []),
    ];
}

export function navigationItemsFor(
    areas: readonly { role: string }[],
): NavItem[] {
    return navigationSectionsFor(areas).flatMap((section) => section.items);
}

function pathOf(url: string): string {
    return new URL(url, 'http://localhost').pathname.replace(/\/$/, '') || '/';
}

export function currentNavigationItem(
    items: readonly NavItem[],
    currentUrl: string,
): NavItem | undefined {
    const currentPath = pathOf(currentUrl);

    const matchingDestination = (item: NavItem): string | undefined =>
        [pathOf(toUrl(item.href)), ...(item.alsoActiveOn ?? [])]
            .filter(
                (destination) =>
                    currentPath === destination ||
                    currentPath.startsWith(`${destination}/`),
            )
            .sort((left, right) => right.length - left.length)[0];

    return items
        .map((item) => ({ item, destination: matchingDestination(item) }))
        .filter(
            (match): match is { item: NavItem; destination: string } =>
                match.destination !== undefined,
        )
        .sort(
            (left, right) => right.destination.length - left.destination.length,
        )[0]?.item;
}

export function useAppNavigation() {
    const page = usePage();
    const areas = computed(() => page.props.roleAreas ?? []);
    const sections = computed(() => navigationSectionsFor(areas.value));
    const items = computed(() =>
        sections.value.flatMap((section) => section.items),
    );
    const currentItem = computed(() =>
        currentNavigationItem(items.value, page.url),
    );

    return { areas, sections, items, currentItem };
}
