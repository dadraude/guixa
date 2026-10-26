<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import StockProductController from '@/actions/App/Http/Controllers/Manage/StockProductController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { trans } from 'laravel-vue-i18n';
import StockProductActions from './StockProductActions.vue';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Stock', href: StockProductController.index() }],
    },
});

defineProps<{
    products: App.Http.StockLevelRow[];
}>();
</script>

<template>
    <Head title="Stock" />

    <div class="pilot-page pilot-layout-page pilot-manager-page">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Stock"
                description="Products kept at the cooperative and sold at pickup"
            />
            <Button as-child>
                <Link :href="StockProductController.create()">New product</Link>
            </Button>
        </div>

        <p
            v-if="products.length === 0"
            class="pilot-layout-notice text-sm text-muted-foreground"
        >
            No stock products yet.
        </p>

        <ul v-else class="pilot-stock-mobile-list sm:hidden">
            <li v-for="product in products" :key="product.id">
                <details>
                    <summary class="pilot-stock-mobile-summary">
                        <span class="min-w-0">
                            <span class="block font-semibold">{{
                                product.name
                            }}</span>
                            <span class="block text-xs text-muted-foreground">
                                {{ product.unit
                                }}<span v-if="product.variable">
                                    (variable)</span
                                >
                                <span v-if="!product.active">
                                    · Deactivated</span
                                >
                            </span>
                        </span>
                        <span class="text-right tabular-nums">
                            <span class="block font-semibold">
                                {{ product.available }}
                                {{ trans('Available').toLowerCase() }}
                            </span>
                            <span class="block text-xs text-muted-foreground">
                                {{ product.price }} EUR
                            </span>
                        </span>
                        <ChevronDown
                            class="size-4 shrink-0"
                            aria-hidden="true"
                        />
                    </summary>
                    <div class="pilot-stock-mobile-details">
                        <dl class="grid grid-cols-2 gap-2 text-sm">
                            <div>
                                <dt class="text-muted-foreground">
                                    {{ trans('Containers per unit') }}
                                </dt>
                                <dd>{{ product.containersPerUnit ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">
                                    {{ trans('Status') }}
                                </dt>
                                <dd>
                                    {{
                                        product.active
                                            ? 'Active'
                                            : 'Deactivated'
                                    }}
                                </dd>
                            </div>
                        </dl>
                        <StockProductActions :product="product" mobile />
                    </div>
                </details>
            </li>
        </ul>

        <div
            v-if="products.length > 0"
            class="pilot-manager-list hidden sm:block"
        >
            <table class="pilot-data-table pilot-manager-table w-full text-sm">
                <thead>
                    <tr class="border-b text-left">
                        <th scope="col" class="py-2">{{ trans('Name') }}</th>
                        <th scope="col" class="py-2">{{ trans('Unit') }}</th>
                        <th scope="col" class="py-2">{{ trans('Price') }}</th>
                        <th scope="col" class="py-2">
                            {{ trans('Containers per unit') }}
                        </th>
                        <th scope="col" class="py-2">
                            {{ trans('Available') }}
                        </th>
                        <th scope="col" class="py-2">{{ trans('Status') }}</th>
                        <th scope="col" class="py-2" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="product in products"
                        :key="product.id"
                        class="border-b"
                        :class="{ 'text-muted-foreground': !product.active }"
                    >
                        <td class="py-2 font-medium">
                            <span class="pilot-cell-label" aria-hidden="true">{{
                                trans('Name')
                            }}</span
                            >{{ product.name }}
                        </td>
                        <td class="py-2">
                            <span class="pilot-cell-label" aria-hidden="true">{{
                                trans('Unit')
                            }}</span>
                            {{ product.unit
                            }}<span v-if="product.variable"> (variable)</span>
                        </td>
                        <td class="py-2 tabular-nums">
                            <span class="pilot-cell-label" aria-hidden="true">{{
                                trans('Price')
                            }}</span
                            >{{ product.price }} EUR
                        </td>
                        <td class="py-2">
                            <span class="pilot-cell-label" aria-hidden="true">{{
                                trans('Containers per unit')
                            }}</span
                            >{{ product.containersPerUnit ?? '-' }}
                        </td>
                        <td class="py-2 tabular-nums">
                            <span class="pilot-cell-label" aria-hidden="true">{{
                                trans('Available')
                            }}</span
                            >{{ product.available }}
                        </td>
                        <td class="py-2">
                            <span class="pilot-cell-label" aria-hidden="true">{{
                                trans('Status')
                            }}</span>
                            {{ product.active ? 'Active' : 'Deactivated' }}
                        </td>
                        <td class="py-2">
                            <StockProductActions :product="product" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
