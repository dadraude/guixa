<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import AdministratorController from '@/actions/App/Http/Controllers/Admin/AdministratorController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import { home } from '@/routes/admin';
import { index } from '@/routes/admin/administrators';

type AdministratorRow = {
    id: string;
    name: string;
    email: string;
    is_owner: boolean;
};

type Candidate = {
    id: string;
    name: string;
    email: string;
};

defineProps<{
    administrators: AdministratorRow[];
    candidates: Candidate[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: t('Administration'), href: home() },
            { title: t('Administrators'), href: index() },
        ],
    },
});
</script>

<template>
    <Head :title="t('Administrators')" />

    <div class="flex h-full flex-1 flex-col gap-8 p-4">
        <Heading
            :title="t('Administrators')"
            :description="
                t(
                    'Only the owner adds the administrator profile to active members and removes it.',
                )
            "
        />

        <ul class="divide-y rounded-lg border" data-test="administrators">
            <li
                v-for="administrator in administrators"
                :key="administrator.id"
                class="flex flex-wrap items-center justify-between gap-3 p-4"
            >
                <div class="flex flex-col">
                    <span class="font-medium">{{ administrator.name }}</span>
                    <span class="text-sm text-muted-foreground">{{
                        administrator.email
                    }}</span>
                </div>

                <div class="flex items-center gap-2">
                    <Badge v-if="administrator.is_owner">{{
                        t('Owner')
                    }}</Badge>

                    <Form
                        v-else
                        v-bind="
                            AdministratorController.destroy.form(
                                administrator.id,
                            )
                        "
                    >
                        <Button type="submit" variant="outline" size="sm">{{
                            t('Remove')
                        }}</Button>
                    </Form>
                </div>
            </li>
        </ul>

        <div class="flex flex-col gap-6">
            <Heading
                variant="small"
                :title="t('Add administrator')"
                :description="
                    t(
                        'They are not sent any email: they act as administrator the next time they choose a profile or sign in.',
                    )
                "
            />

            <p
                v-if="candidates.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ t('There are no active members left to add.') }}
            </p>

            <Form
                v-else
                v-bind="AdministratorController.store.form()"
                class="space-y-6"
                reset-on-success
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="member_id">{{ t('Choose a member') }}</Label>
                    <select
                        id="member_id"
                        name="member_id"
                        required
                        class="h-9 rounded-md border bg-transparent px-3 text-sm"
                    >
                        <option value="" disabled selected>
                            {{ t('Choose a member') }}
                        </option>
                        <option
                            v-for="candidate in candidates"
                            :key="candidate.id"
                            :value="candidate.id"
                        >
                            {{ candidate.name }} ({{ candidate.email }})
                        </option>
                    </select>
                    <InputError :message="errors.member_id" />
                </div>

                <Button :disabled="processing" data-test="add-administrator">{{
                    t('Add administrator')
                }}</Button>
            </Form>
        </div>
    </div>
</template>
