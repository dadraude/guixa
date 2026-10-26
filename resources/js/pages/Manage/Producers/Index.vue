<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import ProducerAccountController from '@/actions/App/Http/Controllers/Manage/ProducerAccountController';
import ProducerController from '@/actions/App/Http/Controllers/Manage/ProducerController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Producers', href: ProducerController.index() }],
    },
});

defineProps<{
    producers: App.Http.ProducerRow[];
}>();

const daysLabel = (days: number) => (days === 1 ? '1 day' : `${days} days`);
</script>

<template>
    <Head title="Producers" />

    <div class="pilot-page pilot-layout-page pilot-manager-page">
        <Heading
            title="Producers"
            description="Suppliers, their default cutoff and their login account"
        />

        <section class="flex flex-col gap-4">
            <h2 class="text-lg font-medium">New producer</h2>

            <Form
                v-bind="ProducerController.store.form()"
                :reset-on-success="['name']"
                class="flex flex-wrap items-end gap-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="producer-name">Name</Label>
                    <Input id="producer-name" name="name" required />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="producer-days"
                        >Cutoff: days before pickup</Label
                    >
                    <Input
                        id="producer-days"
                        name="cutoff_days_before"
                        type="number"
                        min="1"
                        max="6"
                        default-value="3"
                        required
                    />
                    <InputError :message="errors.cutoff_days_before" />
                </div>
                <div class="grid gap-2">
                    <Label for="producer-time">Cutoff time</Label>
                    <Input
                        id="producer-time"
                        name="cutoff_time"
                        type="time"
                        default-value="20:00"
                        required
                    />
                    <InputError
                        :message="errors.cutoff_time || errors.domain"
                    />
                </div>
                <Button
                    type="submit"
                    :disabled="processing"
                    data-test="create-producer"
                >
                    Create
                </Button>
            </Form>
        </section>

        <p v-if="producers.length === 0" class="text-sm text-muted-foreground">
            No producers yet.
        </p>

        <ul v-else class="pilot-list">
            <li
                v-for="producer in producers"
                :key="producer.id"
                class="flex flex-col gap-4"
                :class="{ 'text-muted-foreground': !producer.active }"
            >
                <div class="flex items-center justify-between gap-2">
                    <span class="font-medium">{{ producer.name }}</span>
                    <span class="text-sm text-muted-foreground">
                        Cutoff {{ daysLabel(producer.cutoffDaysBefore) }} before
                        pickup, at {{ producer.cutoffTime }}
                    </span>
                </div>

                <p
                    v-if="producer.accountEmail !== null"
                    class="text-sm"
                    :data-test="`account-${producer.id}`"
                >
                    Account: {{ producer.accountEmail }}
                    <span class="text-muted-foreground">
                        ({{
                            producer.accountInvitationPending
                                ? 'invitation pending'
                                : 'active'
                        }})
                    </span>
                </p>

                <Form
                    v-else
                    v-bind="ProducerAccountController.store.form(producer.id)"
                    :reset-on-success="['name', 'email']"
                    class="flex flex-wrap items-end gap-6"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-1">
                        <Input
                            name="name"
                            required
                            placeholder="Account name"
                            class="h-8"
                        />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-1">
                        <Input
                            name="email"
                            type="email"
                            required
                            placeholder="Account email"
                            class="h-8"
                        />
                        <InputError :message="errors.email || errors.domain" />
                    </div>
                    <Button
                        type="submit"
                        variant="outline"
                        size="sm"
                        :disabled="processing"
                        :data-test="`invite-${producer.id}`"
                    >
                        Invite account
                    </Button>
                </Form>
            </li>
        </ul>
    </div>
</template>
