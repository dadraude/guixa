<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Pencil, Plus } from '@lucide/vue';
import ProductController from '@/actions/App/Http/Controllers/Producer/ProductController';
import Heading from '@/components/Heading.vue';
import ActiveSwitch from '@/components/ActiveSwitch.vue';
import { Button } from '@/components/ui/button';
import { trans } from 'laravel-vue-i18n';
import { formatMoney, formatQuantity } from '@/lib/locale';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Products', href: ProductController.index() }],
    },
});

defineProps<{
    products: App.Http.ProductRow[];
}>();
</script>

<template>
    <Head :title="trans('Products')" />

    <div class="pilot-layout-page pilot-t44-page">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="trans('Products')"
                :description="trans('What you offer to the cooperative')"
            />
            <Button as-child>
                <Link :href="ProductController.create()">
                    <Plus aria-hidden="true" />
                    {{ trans('New product') }}
                </Link>
            </Button>
        </div>

        <p
            v-if="products.length === 0"
            class="pilot-layout-notice text-sm text-muted-foreground"
        >
            {{ trans('No products yet.') }}
        </p>

        <div v-else class="pilot-product-list">
            <table class="pilot-data-table pilot-product-table w-full text-sm">
                <thead>
                    <tr class="border-b text-left">
                        <th scope="col" class="py-2">{{ trans('Active') }}</th>
                        <th scope="col" class="py-2">{{ trans('Name') }}</th>
                        <th scope="col" class="py-2">{{ trans('Unit') }}</th>
                        <th scope="col" class="py-2">{{ trans('Price') }}</th>
                        <th scope="col" class="py-2">
                            {{ trans('Containers per unit') }}
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
                                        ? ProductController.destroy.form(
                                              product.id,
                                          )
                                        : ProductController.reactivate.form(
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
                            }}<span v-if="product.variable">
                                {{ trans('(variable)') }}</span
                            >
                        </td>
                        <td class="pilot-amount py-2">
                            <span class="pilot-cell-label" aria-hidden="true">{{
                                trans('Price')
                            }}</span
                            >{{ formatMoney(product.price) }}
                        </td>
                        <td class="pilot-amount py-2">
                            <span class="pilot-cell-label" aria-hidden="true">{{
                                trans('Containers per unit')
                            }}</span
                            >{{
                                product.containersPerUnit === null
                                    ? '-'
                                    : formatQuantity(product.containersPerUnit)
                            }}
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
                                            ProductController.edit(product.id)
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
