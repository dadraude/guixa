<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import HouseholdController from '@/actions/App/Http/Controllers/Households/HouseholdController';
import Heading from '@/components/Heading.vue';
import HouseholdStatusAction from '@/components/households/HouseholdStatusAction.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import { index } from '@/routes/households';

type HouseholdRow = {
    id: string;
    number: number;
    name: string;
    is_active: boolean;
    without_commission: boolean;
    affected_members: { name: string; left_out: boolean }[];
};

defineProps<{
    households: HouseholdRow[];
    status?: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('Households'), href: index() }],
    },
});
</script>

<template>
    <Head :title="t('Households')" />

    <div class="flex h-full flex-1 flex-col gap-8 p-4">
        <Heading
            :title="t('Households')"
            :description="
                t('The number is assigned automatically and never changes.')
            "
        />

        <p
            v-if="status"
            class="text-sm font-medium text-green-600"
            data-test="status"
        >
            {{ status }}
        </p>

        <p v-if="households.length === 0" class="text-muted-foreground">
            {{ t('No households yet.') }}
        </p>

        <ul v-else class="divide-y rounded-lg border" data-test="households">
            <li
                v-for="household in households"
                :key="household.id"
                class="flex flex-wrap items-center justify-between gap-3 p-4"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="font-mono text-sm text-muted-foreground"
                        :title="t('Household number')"
                        >#{{ household.number }}</span
                    >
                    <Form
                        v-bind="HouseholdController.update.form(household.id)"
                        class="flex items-center gap-2"
                        v-slot="{ errors, processing }"
                    >
                        <Input
                            name="name"
                            required
                            :aria-label="t('Household name')"
                            :default-value="household.name"
                        />
                        <Button
                            type="submit"
                            variant="outline"
                            size="sm"
                            :disabled="processing"
                            >{{ t('Rename') }}</Button
                        >
                        <InputError :message="errors.name" />
                    </Form>
                </div>

                <div class="flex items-center gap-2">
                    <Badge
                        v-if="household.without_commission"
                        variant="outline"
                        class="border-amber-500 text-amber-700"
                        :title="
                            t(
                                'No active member of this household is in a commission.',
                            )
                        "
                        data-test="without-commission"
                        >{{ t('No members in any commission') }}</Badge
                    >
                    <Badge
                        :variant="household.is_active ? 'secondary' : 'outline'"
                    >
                        {{ household.is_active ? t('Active') : t('Inactive') }}
                    </Badge>
                </div>

                <HouseholdStatusAction
                    :household-id="household.id"
                    :is-active="household.is_active"
                    :affected-members="household.affected_members"
                />
            </li>
        </ul>

        <div class="flex flex-col gap-6">
            <Heading variant="small" :title="t('Add household')" />

            <Form
                v-bind="HouseholdController.store.form()"
                class="space-y-6"
                reset-on-success
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="name">{{ t('Name') }}</Label>
                    <Input
                        id="name"
                        name="name"
                        required
                        autocomplete="off"
                        :placeholder="t('Household name')"
                    />
                    <InputError :message="errors.name" />
                </div>

                <Button :disabled="processing" data-test="add-household">{{
                    t('Add household')
                }}</Button>
            </Form>
        </div>
    </div>
</template>
