<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import StockProductController from '@/actions/App/Http/Controllers/Manage/StockProductController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Stock', href: StockProductController.index() }],
    },
});

defineProps<{
    products: App.Http.StockProductRow[];
}>();
</script>

<template>
    <Head title="Stock" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <Heading
                title="Stock"
                description="Products kept at the cooperative and sold at pickup"
            />
            <Button as-child>
                <Link :href="StockProductController.create()">New product</Link>
            </Button>
        </div>

        <p v-if="products.length === 0" class="text-sm text-muted-foreground">
            No stock products yet.
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
                            <Link
                                :href="StockProductController.edit(product.id)"
                                >Edit</Link
                            >
                        </Button>
                        <Form
                            v-if="product.active"
                            v-bind="
                                StockProductController.destroy.form(product.id)
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
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
