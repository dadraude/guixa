<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import StockMovementController from '@/actions/App/Http/Controllers/Manage/StockMovementController';
import StockProductController from '@/actions/App/Http/Controllers/Manage/StockProductController';
import { Button } from '@/components/ui/button';

defineProps<{
    product: App.Http.StockLevelRow;
    mobile?: boolean;
}>();
</script>

<template>
    <div class="pilot-action-row justify-start sm:justify-end">
        <Button variant="outline" size="sm" class="min-h-11" as-child>
            <Link :href="StockMovementController.show(product.id)">
                Movements
            </Link>
        </Button>
        <Button variant="outline" size="sm" class="min-h-11" as-child>
            <Link :href="StockProductController.edit(product.id)">Edit</Link>
        </Button>
        <Form
            v-if="product.active"
            v-bind="StockProductController.destroy.form(product.id)"
        >
            <Button
                type="submit"
                variant="outline"
                size="sm"
                class="min-h-11"
                :data-test="`deactivate-${mobile ? 'mobile-' : ''}${product.id}`"
            >
                Deactivate
            </Button>
        </Form>
    </div>
</template>
