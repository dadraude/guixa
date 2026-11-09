<script setup lang="ts">
import StockProductFields from '@/components/StockProductFields.vue';
import { Button } from '@/components/ui/button';

defineProps<{
    product?: Pick<
        App.Http.ProductRow,
        'name' | 'unit' | 'price' | 'variable' | 'containersPerUnit'
    >;
    errors: Partial<Record<string, string>>;
    processing: boolean;
    submitLabel: string;
}>();
</script>

<template>
    <StockProductFields :product="product" :errors="errors" />

    <Button type="submit" :disabled="processing" data-test="save-product">
        {{ submitLabel }}
    </Button>
</template>
