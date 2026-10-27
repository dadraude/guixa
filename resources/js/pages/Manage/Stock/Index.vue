<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { History, Pencil, Plus } from '@lucide/vue';
import StockMovementController from '@/actions/App/Http/Controllers/Manage/StockMovementController';
import StockProductController from '@/actions/App/Http/Controllers/Manage/StockProductController';
import Heading from '@/components/Heading.vue';
import ActiveSwitch from '@/components/ActiveSwitch.vue';
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

    <div class="pilot-page pilot-manager-page">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Stock"
                description="Products kept at the cooperative and sold at pickup"
            />
            <Button as-child>
                <Link :href="StockProductController.create()">
                    <Plus aria-hidden="true" />
                    New product
                </Link>
            </Button>
        </div>

        <p
            v-if="products.length === 0"
            class="pilot-empty-state text-sm text-muted-foreground"
        >
            No stock products yet.
        </p>

        <div v-else class="pilot-manager-list">
            <table class="pilot-data-table pilot-manager-table w-full text-sm">
                <thead>
                    <tr class="border-b text-left">
                        <th scope="col" class="py-2">{{ trans('Active') }}</th>
                        <th scope="col" class="py-2">{{ trans('Name') }}</th>
                        <th scope="col" class="py-2">{{ trans('Unit') }}</th>
                        <th scope="col" class="py-2">{{ trans('Price') }}</th>
                        <th scope="col" class="py-2">
                            {{ trans('Containers per unit') }}
                        </th>
                        <th scope="col" class="py-2">
                            {{ trans('Available') }}
                        </th>
                        <th scope="col" class="py-2">{{ trans('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="product in products"
                        :key="product.id"
                        class="border-b"
                        :class="{ 'text-muted-foreground': !product.active }"
                    >
                        <td class="py-2">
                            <span class="pilot-cell-label" aria-hidden="true">{{
                                trans('Active')
                            }}</span>
                            <Form
                                v-bind="
                                    product.active
                                        ? StockProductController.destroy.form(
                                              product.id,
                                          )
                                        : StockProductController.reactivate.form(
                                              product.id,
                                          )
                                "
                                v-slot="{ processing }"
                                class="pilot-status-switch-form"
                            >
                                <ActiveSwitch
                                    :id="product.id"
                                    :active="product.active"
                                    :name="product.name"
                                    :processing="processing"
                                />
                            </Form>
                        </td>
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
                                trans('Actions')
                            }}</span>
                            <div class="pilot-table-actions">
                                <Button
                                    variant="outline"
                                    size="icon"
                                    class="size-11"
                                    as-child
                                >
                                    <Link
                                        :href="
                                            StockMovementController.show(
                                                product.id,
                                            )
                                        "
                                        :aria-label="`${trans('Movements')}: ${product.name}`"
                                        :title="trans('Movements')"
                                    >
                                        <History aria-hidden="true" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    class="size-11"
                                    as-child
                                >
                                    <Link
                                        :href="
                                            StockProductController.edit(
                                                product.id,
                                            )
                                        "
                                        :aria-label="`${trans('Edit')}: ${product.name}`"
                                        :title="trans('Edit')"
                                    >
                                        <Pencil aria-hidden="true" />
                                    </Link>
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
