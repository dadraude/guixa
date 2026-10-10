<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import HouseholdController from '@/actions/App/Http/Controllers/Households/HouseholdController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { t } from '@/lib/i18n';

type AffectedMember = { id: string; name: string; left_out: boolean };

const props = defineProps<{
    householdId: string;
    isActive: boolean;
    affectedMembers: AffectedMember[];
}>();

// Nothing is preselected: the person has to choose before confirming.
const withMembers = ref<'' | '0' | '1'>('');
const open = ref(false);
// Set when the server rejected a request sent without a choice: the members changed after the list loaded.
const membersChanged = ref(false);

// Inertia's <Form> keeps its state after a visit, so a previous choice would come back preselected.
watch(open, (isOpen) => {
    if (isOpen) {
        withMembers.value = '';
        return;
    }
    membersChanged.value = false;
});

// The redirect reloads the props with the members now affected: the dialog replaces this form, so open it.
const askAgain = (errors: Record<string, string>) => {
    if (!errors.with_members) {
        return;
    }
    membersChanged.value = true;
    open.value = true;
};

const action = computed(() =>
    props.isActive
        ? HouseholdController.deactivate.form(props.householdId)
        : HouseholdController.reactivate.form(props.householdId),
);
const hasQuestion = computed(() => props.affectedMembers.length > 0);
const leftOut = computed(() =>
    props.affectedMembers.filter((member) => member.left_out),
);
</script>

<template>
    <!-- Nobody to affect: the question is skipped and only the household changes. -->
    <Form
        v-if="!hasQuestion"
        v-bind="action"
        v-slot="{ processing }"
        data-test="household-status-form"
        @error="askAgain"
    >
        <Button
            type="submit"
            variant="outline"
            size="sm"
            :disabled="processing"
            :data-test="
                isActive ? 'deactivate-household' : 'reactivate-household'
            "
            >{{ isActive ? t('Deactivate') : t('Reactivate') }}</Button
        >
    </Form>

    <Dialog v-else v-model:open="open">
        <DialogTrigger as-child>
            <Button
                type="button"
                variant="outline"
                size="sm"
                :data-test="
                    isActive ? 'deactivate-household' : 'reactivate-household'
                "
                >{{ isActive ? t('Deactivate') : t('Reactivate') }}</Button
            >
        </DialogTrigger>
        <DialogContent>
            <Form
                v-bind="action"
                class="space-y-4"
                v-slot="{ errors, processing }"
                @success="open = false"
            >
                <DialogHeader>
                    <DialogTitle>{{
                        isActive
                            ? t('Deactivate household')
                            : t('Reactivate household')
                    }}</DialogTitle>
                    <DialogDescription>{{
                        t('Choose what happens to the members')
                    }}</DialogDescription>
                </DialogHeader>

                <p
                    v-if="membersChanged"
                    class="text-sm text-destructive"
                    data-test="members-changed-notice"
                >
                    {{
                        t(
                            'The members of this household changed. Review them and choose again.',
                        )
                    }}
                </p>

                <fieldset class="space-y-2">
                    <label class="flex items-center gap-2 text-sm">
                        <input
                            v-model="withMembers"
                            type="radio"
                            name="with_members"
                            value="0"
                            data-test="only-household"
                        />
                        {{
                            isActive
                                ? t('Deactivate only the household')
                                : t('Reactivate only the household')
                        }}
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input
                            v-model="withMembers"
                            type="radio"
                            name="with_members"
                            value="1"
                            data-test="with-members"
                        />
                        {{
                            isActive
                                ? t('Deactivate the household and its members')
                                : t('Reactivate the household and its members')
                        }}
                    </label>
                    <InputError :message="errors.with_members" />
                </fieldset>

                <div class="space-y-1 text-sm">
                    <p class="font-medium">{{ t('Members affected') }}</p>
                    <ul class="list-disc pl-5" data-test="affected-members">
                        <li
                            v-for="member in affectedMembers"
                            :key="member.id"
                            :class="member.left_out && 'text-muted-foreground'"
                        >
                            {{ member.name }}
                        </li>
                    </ul>
                    <p
                        v-if="leftOut.length > 0"
                        class="text-muted-foreground"
                        data-test="left-out-note"
                    >
                        {{
                            t(
                                'These members will stay as they are because you may not change them on your own.',
                            )
                        }}
                        {{ leftOut.map((member) => member.name).join(', ') }}
                    </p>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                        >{{ t('Cancel') }}</Button
                    >
                    <Button
                        type="submit"
                        :disabled="processing || withMembers === ''"
                        data-test="confirm-household-status"
                        >{{
                            isActive ? t('Deactivate') : t('Reactivate')
                        }}</Button
                    >
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
