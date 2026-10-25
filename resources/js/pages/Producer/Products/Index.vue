<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import ProductController from '@/actions/App/Http/Controllers/Producer/ProductController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { trans } from 'laravel-vue-i18n';

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
    <Head title="Products" />

    <div class="pilot-page">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Products"
                description="What you offer to the cooperative"
            />
            <Button as-child>
                <Link :href="ProductController.create()">New product</Link>
            </Button>
        </div>

        <p
            v-if="products.length === 0"
            class="pilot-empty-state text-sm text-muted-foreground"
        >
            No products yet.
        </p>

        <div v-else class="pilot-product-list">
            <table class="pilot-data-table pilot-product-table w-full text-sm">
                <thead>
                    <tr class="border-b text-left">
                        <th scope="col" class="py-2">{{ trans('Name') }}</th>
                        <th scope="col" class="py-2">{{ trans('Unit') }}</th>
                        <th scope="col" class="py-2">{{ trans('Price') }}</th>
                        <th scope="col" class="py-2">
                            {{ trans('Containers per unit') }}
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
                        <td class="py-2">
                            <span class="pilot-cell-label" aria-hidden="true">{{
                                trans('Status')
                            }}</span>
                            {{ product.active ? 'Active' : 'Deactivated' }}
                        </td>
                        <td class="py-2">
                            <div class="flex flex-wrap justify-end gap-2">
                                <Button variant="outline" size="sm" as-child>
                                    <Link
                                        :href="
                                            ProductController.edit(product.id)
                                        "
                                        >Edit</Link
                                    >
                                </Button>
                                <Form
                                    v-if="product.active"
                                    v-bind="
                                        ProductController.destroy.form(
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
                                <Form
                                    v-else
                                    v-bind="
                                        ProductController.reactivate.form(
                                            product.id,
                                        )
                                    "
                                >
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        size="sm"
                                        :data-test="`reactivate-${product.id}`"
                                    >
                                        Reactivate
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
