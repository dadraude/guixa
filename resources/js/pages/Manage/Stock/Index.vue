<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, shallowRef } from 'vue';
import { History, Pencil, Plus } from '@lucide/vue';
import StockProductController from '@/actions/App/Http/Controllers/Manage/StockProductController';
import StockMovementController from '@/actions/App/Http/Controllers/Manage/StockMovementController';
import StockEditDialog from './StockEditDialog.vue';
import Heading from '@/components/Heading.vue';
import ActiveSwitch from '@/components/ActiveSwitch.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatEuroAmount } from '@/lib/formatEuroAmount';
import { trans } from 'laravel-vue-i18n';

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
        <div class="flex flex-wrap items-end justify-between gap-4">
            <Heading
                title="Estoc"
                description="Productes per a la recollida."
            />
            <Button as-child class="min-h-11">
                <Link :href="StockProductController.create()">
                    <Plus aria-hidden="true" />
                    Afegeix un producte
                </Link>
            </Button>
        </div>
        <p
            v-if="products.length === 0"
            class="pilot-layout-notice text-sm text-muted-foreground"
        >
            Encara no hi ha productes d’estoc.
        </p>
        <section v-else class="manager-stock-list">
            <div class="flex flex-wrap items-end justify-between gap-4 pb-4">
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
                <li
                    v-for="product in filteredProducts"
                    :key="product.id"
                    class="flex min-w-0 items-center gap-4"
                >
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
                        class="pilot-status-switch-form shrink-0"
                    >
                        <ActiveSwitch
                            :id="product.id"
                            :active="product.active"
                            :name="product.name"
                            :processing="processing"
                        />
                    </Form>
                    <button
                        type="button"
                        class="flex min-h-16 min-w-0 flex-1 items-center justify-between gap-4 py-2 text-left"
                        :aria-label="`Edita ${product.name}`"
                        @click="editProduct(product, $event)"
                    >
                        <span class="min-w-0">
                            <span class="block truncate font-medium">{{
                                product.name
                            }}</span>
                            <span class="block text-xs text-muted-foreground"
                                >{{ product.unit }} ·
                                {{ formatEuroAmount(product.price) }}</span
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
                            <th scope="col" class="px-2 py-2">
                                {{ trans('Active') }}
                            </th>
                            <th scope="col" class="px-2 py-2">Producte</th>
                            <th scope="col" class="px-2 py-2">Unitat</th>
                            <th scope="col" class="px-2 py-2">Preu</th>
                            <th scope="col" class="px-2 py-2">Disponible</th>
                            <th scope="col" class="px-2 py-2">Accions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="product in filteredProducts"
                            :key="product.id"
                        >
                            <td class="px-2 py-2">
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
                            <th scope="row" class="px-2 py-2 font-medium">
                                {{ product.name }}
                            </th>
                            <td class="px-2 py-2">{{ product.unit }}</td>
                            <td class="px-2 py-2 tabular-nums">
                                {{ formatEuroAmount(product.price) }}
                            </td>
                            <td class="px-2 py-2 tabular-nums">
                                {{ product.available }}
                            </td>
                            <td class="px-2 py-2">
                                <div class="pilot-table-actions">
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        class="size-11"
                                        :aria-label="`${trans('Edit')}: ${product.name}`"
                                        :title="trans('Edit')"
                                        @click="editProduct(product, $event)"
                                    >
                                        <Pencil aria-hidden="true" />
                                    </Button>
                                    <Button
                                        as-child
                                        variant="outline"
                                        size="icon"
                                        class="size-11"
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
