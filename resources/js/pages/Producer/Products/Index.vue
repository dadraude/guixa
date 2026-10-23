<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import ProductController from '@/actions/App/Http/Controllers/Producer/ProductController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';

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

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <Heading
                title="Products"
                description="What you offer to the cooperative"
            />
            <Button as-child>
                <Link :href="ProductController.create()">New product</Link>
            </Button>
        </div>

        <p v-if="products.length === 0" class="text-sm text-muted-foreground">
            No products yet.
        </p>

        <table v-else class="w-full text-sm">
            <thead>
                <tr class="border-b text-left">
                    <th class="py-2">Name</th>
                    <th class="py-2">Unit</th>
                    <th class="py-2">Price</th>
                    <th class="py-2">Containers per unit</th>
                    <th class="py-2">Status</th>
                    <th class="py-2" />
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="product in products"
                    :key="product.id"
                    class="border-b"
                    :class="{ 'text-muted-foreground': !product.active }"
                >
                    <td class="py-2">{{ product.name }}</td>
                    <td class="py-2">
                        {{ product.unit
                        }}<span v-if="product.variable"> (variable)</span>
                    </td>
                    <td class="py-2">{{ product.price }} EUR</td>
                    <td class="py-2">{{ product.containersPerUnit ?? '-' }}</td>
                    <td class="py-2">
                        {{ product.active ? 'Active' : 'Deactivated' }}
                    </td>
                    <td class="flex justify-end gap-2 py-2">
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="ProductController.edit(product.id)"
                                >Edit</Link
                            >
                        </Button>
                        <Form
                            v-if="product.active"
                            v-bind="ProductController.destroy.form(product.id)"
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
                                ProductController.reactivate.form(product.id)
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
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
