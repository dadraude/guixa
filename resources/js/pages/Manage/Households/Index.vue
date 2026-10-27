<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import SettlementDetails from './SettlementDetails.vue';
import HouseholdController from '@/actions/App/Http/Controllers/Manage/HouseholdController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Households', href: HouseholdController.index() },
        ],
    },
});

defineProps<{
    households: App.Http.HouseholdSummaryRow[];
    toSettle: App.Http.HouseholdSettlementRow[];
}>();
</script>

<template>
    <Head title="Households" />

    <div class="pilot-page pilot-layout-page pilot-manager-page">
        <Heading
            title="Households"
            description="Create households and invite their members"
        />

        <Form
            v-bind="HouseholdController.store.form()"
            :reset-on-success="['name']"
            class="flex items-end gap-2"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">New household</Label>
                <Input id="name" name="name" required placeholder="Name" />
                <InputError :message="errors.name || errors.domain" />
            </div>
            <Button type="submit" :disabled="processing">Create</Button>
        </Form>

        <section
            v-if="toSettle.length > 0"
            class="grid gap-4"
            aria-labelledby="to-settle-heading"
        >
            <h2 id="to-settle-heading" class="text-lg font-medium">
                {{ trans('To settle') }}
            </h2>
            <ul class="grid gap-4">
                <li
                    v-for="settlement in toSettle"
                    :key="settlement.householdId"
                    class="min-w-0 rounded-lg border p-4"
                >
                    <Link
                        :href="HouseholdController.show(settlement.householdId)"
                        class="inline-flex min-h-11 min-w-11 items-center justify-center font-medium underline underline-offset-4"
                    >
                        {{ settlement.householdName }}
                    </Link>
                    <SettlementDetails :settlement="settlement" />
                </li>
            </ul>
        </section>

        <p v-if="households.length === 0" class="text-sm text-muted-foreground">
            There are no households yet.
        </p>

        <ul v-else class="pilot-list">
            <li
                v-for="household in households"
                :key="household.id"
                class="flex items-center justify-between gap-4"
            >
                <Link
                    :href="HouseholdController.show(household.id)"
                    class="font-medium underline-offset-4 hover:underline"
                >
                    {{ household.name }}
                </Link>
                <span
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    {{ household.memberCount }} members
                    <Badge v-if="!household.active" variant="secondary">
                        Inactive
                    </Badge>
                </span>
            </li>
        </ul>
    </div>
</template>
