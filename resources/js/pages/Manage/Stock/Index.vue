<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, shallowRef } from 'vue';
import { Pencil } from '@lucide/vue';
import StockProductController from '@/actions/App/Http/Controllers/Manage/StockProductController';
import StockMovementController from '@/actions/App/Http/Controllers/Manage/StockMovementController';
import StockEditDialog from './StockEditDialog.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatEuroAmount } from '@/lib/formatEuroAmount';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Estoc', href: StockProductController.index() }],
    },
});
const props = defineProps<{ products: App.Http.StockLevelRow[] }>();
const search = shallowRef('');
const selectedProduct = shallowRef<App.Http.StockLevelRow | null>(null);
const dialogOpen = shallowRef(false);
const dialogTrigger = shallowRef<HTMLElement | null>(null);
const filteredProducts = computed(() =>
    props.products.filter((product) =>
        product.name
            .toLocaleLowerCase('ca')
            .includes(search.value.trim().toLocaleLowerCase('ca')),
    ),
);
function editProduct(product: App.Http.StockLevelRow, event: MouseEvent) {
    dialogTrigger.value = event.currentTarget as HTMLElement;
    selectedProduct.value = product;
    dialogOpen.value = true;
}
</script>

<template>
    <Head title="Estoc" />
    <div class="pilot-page pilot-layout-page pilot-manager-page">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <Heading
                title="Estoc"
                description="Productes per a la recollida."
            />
            <Button as-child class="min-h-11"
                ><Link :href="StockProductController.create()"
                    >Afegeix un producte</Link
                ></Button
            >
        </div>
        <p
            v-if="products.length === 0"
            class="pilot-layout-notice text-sm text-muted-foreground"
        >
            Encara no hi ha productes d’estoc.
        </p>
        <section v-else class="manager-stock-list">
            <div class="flex flex-wrap items-end justify-between gap-3 pb-3">
                <div>
                    <h2 class="text-lg font-semibold">Productes</h2>
                    <p class="text-sm text-muted-foreground">
                        {{ filteredProducts.length }} de {{ products.length }}
                    </p>
                </div>
                <div class="w-full sm:w-64">
                    <Label for="stock-search">Cerca productes</Label>
                    <Input
                        id="stock-search"
                        v-model="search"
                        type="search"
                        placeholder="Cerca pel nom"
                        class="mt-2"
                    />
                </div>
            </div>
            <p
                v-if="filteredProducts.length === 0"
                class="border-t py-4 text-sm text-muted-foreground"
            >
                No hi ha productes que coincideixin amb la cerca.
            </p>
            <ul v-else class="manager-stock-rows divide-y border-t">
                <li v-for="product in filteredProducts" :key="product.id">
                    <button
                        type="button"
                        class="flex min-h-16 w-full items-center justify-between gap-3 py-2 text-left"
                        :aria-label="`Edita ${product.name}`"
                        @click="editProduct(product, $event)"
                    >
                        <span class="min-w-0">
                            <span class="block truncate font-medium">{{
                                product.name
                            }}</span>
                            <span class="block text-xs text-muted-foreground"
                                >{{ product.unit }} ·
                                {{ formatEuroAmount(product.price) }}
                                <span
                                    v-if="!product.active"
                                    class="text-destructive"
                                    >· Inactiu</span
                                ></span
                            >
                        </span>
                        <span class="shrink-0 text-right">
                            <span class="block font-semibold tabular-nums">{{
                                product.available
                            }}</span>
                            <span
                                class="flex items-center justify-end gap-1 text-xs text-muted-foreground"
                                >disponibles
                                <Pencil class="size-4" aria-hidden="true"
                            /></span>
                        </span>
                    </button>
                </li>
            </ul>
            <div
                v-if="filteredProducts.length"
                class="manager-stock-table overflow-x-auto border-t"
            >
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/50 text-xs uppercase">
                        <tr>
                            <th scope="col" class="px-3 py-2">Producte</th>
                            <th scope="col" class="px-3 py-2">Unitat</th>
                            <th scope="col" class="px-3 py-2">Preu</th>
                            <th scope="col" class="px-3 py-2">Disponible</th>
                            <th scope="col" class="px-3 py-2">Estat</th>
                            <th scope="col" class="px-3 py-2">Accions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="product in filteredProducts"
                            :key="product.id"
                        >
                            <th scope="row" class="px-3 py-2 font-medium">
                                {{ product.name }}
                            </th>
                            <td class="px-3 py-2">{{ product.unit }}</td>
                            <td class="px-3 py-2 tabular-nums">
                                {{ formatEuroAmount(product.price) }}
                            </td>
                            <td class="px-3 py-2 tabular-nums">
                                {{ product.available }}
                            </td>
                            <td class="px-3 py-2">
                                {{ product.active ? 'Actiu' : 'Inactiu' }}
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="min-h-11"
                                        @click="editProduct(product, $event)"
                                        >Edita</Button
                                    >
                                    <Button
                                        as-child
                                        variant="outline"
                                        size="sm"
                                        class="min-h-11"
                                        ><Link
                                            :href="
                                                StockMovementController.show(
                                                    product.id,
                                                )
                                            "
                                            >Moviments</Link
                                        ></Button
                                    >
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
        <StockEditDialog
            :open="dialogOpen"
            :product="selectedProduct"
            :return-focus-to="dialogTrigger"
            @close="dialogOpen = false"
        />
    </div>
</template>
