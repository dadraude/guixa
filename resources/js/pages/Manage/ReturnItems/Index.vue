<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import ReturnItemController from '@/actions/App/Http/Controllers/Manage/ReturnItemController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Return items', href: ReturnItemController.index() },
        ],
    },
});

defineProps<{
    items: App.Http.ReturnItemRow[];
}>();
</script>

<template>
    <Head title="Return items" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <Heading
                title="Return items"
                description="Containers households bring back and get credited for"
            />
            <Button as-child>
                <Link :href="ReturnItemController.create()"
                    >New return item</Link
                >
            </Button>
        </div>

        <p v-if="items.length === 0" class="text-sm text-muted-foreground">
            No return items yet.
        </p>

        <table v-else class="w-full text-sm">
            <thead>
                <tr class="border-b text-left">
                    <th class="py-2">Name</th>
                    <th class="py-2">Amount</th>
                    <th class="py-2">Status</th>
                    <th class="py-2" />
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="item in items"
                    :key="item.id"
                    class="border-b"
                    :class="{ 'text-muted-foreground': !item.active }"
                >
                    <td class="py-2">{{ item.name }}</td>
                    <td class="py-2">{{ item.amount }} EUR</td>
                    <td class="py-2">
                        {{ item.active ? 'Active' : 'Deactivated' }}
                    </td>
                    <td class="flex justify-end gap-2 py-2">
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="ReturnItemController.edit(item.id)"
                                >Edit</Link
                            >
                        </Button>
                        <Form
                            v-if="item.active"
                            v-bind="ReturnItemController.destroy.form(item.id)"
                        >
                            <Button
                                type="submit"
                                variant="outline"
                                size="sm"
                                :data-test="`deactivate-${item.id}`"
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
