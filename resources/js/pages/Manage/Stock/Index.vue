<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import StockMovementController from '@/actions/App/Http/Controllers/Manage/StockMovementController';
import StockProductController from '@/actions/App/Http/Controllers/Manage/StockProductController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { trans } from 'laravel-vue-i18n';

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

        <div v-else class="pilot-manager-list">
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
                            <div class="pilot-action-row justify-end">
                                <Button variant="outline" size="sm" as-child>
                                    <Link
                                        :href="
                                            StockMovementController.show(
                                                product.id,
                                            )
                                        "
                                        >Movements</Link
                                    >
                                </Button>
                                <Button variant="outline" size="sm" as-child>
                                    <Link
                                        :href="
                                            StockProductController.edit(
                                                product.id,
                                            )
                                        "
                                        >Edit</Link
                                    >
                                </Button>
                                <Form
                                    v-if="product.active"
                                    v-bind="
                                        StockProductController.destroy.form(
                                            product.id,
                                        )
                                    "
                                >
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        size="sm"
                                        :data-test="`deactivate-${product.id}`"
                                    >
                                        Deactivate
                                    </Button>
                                </Form>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
