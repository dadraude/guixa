<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import ProducerAccountController from '@/actions/App/Http/Controllers/Manage/ProducerAccountController';
import ProducerController from '@/actions/App/Http/Controllers/Manage/ProducerController';
import StockProductController from '@/actions/App/Http/Controllers/Catalogue/StockProductController';
import ProducerProductController from '@/actions/App/Http/Controllers/Catalogue/ProducerProductController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import StatusIndicator from '@/components/StatusIndicator.vue';
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

const daysLabel = (days: number) =>
    days === 1 ? trans('1 day') : trans(':count days', { count: String(days) });
</script>

<template>
    <Head :title="trans('Producers')" />

    <div class="pilot-page pilot-layout-page pilot-manager-page">
        <Heading
            :title="trans('Producers')"
            :description="
                trans('Suppliers, their default cutoff and their login account')
            "
        />

        <section class="flex flex-col gap-4">
            <h2 class="text-lg font-medium">{{ trans('New producer') }}</h2>

            <Form
                v-bind="ProducerController.store.form()"
                :reset-on-success="['name']"
                class="flex flex-wrap items-end gap-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="producer-name">{{ trans('Name') }}</Label>
                    <Input id="producer-name" name="name" required />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="producer-days">{{
                        trans('Cutoff: days before pickup')
                    }}</Label>
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
                    <Label for="producer-time">{{
                        trans('Cutoff time')
                    }}</Label>
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
                    {{ trans('Create') }}
                </Button>
            </Form>
        </section>

        <p v-if="producers.length === 0" class="text-sm text-muted-foreground">
            {{ trans('No producers yet.') }}
        </p>

        <ul v-else class="pilot-list">
            <li
                v-for="producer in producers"
                :key="producer.id"
                class="flex flex-col gap-4"
                :class="{ 'text-muted-foreground': !producer.active }"
            >
                <div class="flex items-center justify-between gap-2">
                    <span class="flex flex-wrap items-center gap-4">
                        <span class="font-medium">{{ producer.name }}</span>
                        <StatusIndicator
                            :label="
                                producer.active
                                    ? trans('Active')
                                    : trans('Deactivated')
                            "
                            :tone="producer.active ? 'success' : 'danger'"
                        />
                    </span>
                    <span class="text-sm text-muted-foreground">
                        {{
                            trans('Cutoff :days before pickup, at :time', {
                                days: daysLabel(producer.cutoffDaysBefore),
                                time: producer.cutoffTime,
                            })
                        }}
                    </span>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button
                        as-child
                        variant="outline"
                        class="h-auto min-h-11 max-w-full min-w-0 self-start py-2 text-center break-words whitespace-normal"
                    >
                        <Link
                            :href="ProducerProductController.index(producer.id)"
                            :aria-label="
                                trans('View products of :name', {
                                    name: producer.name,
                                })
                            "
                        >
                            {{ trans('View products') }}
                        </Link>
                    </Button>
                    <Button
                        as-child
                        variant="outline"
                        class="h-auto min-h-11 max-w-full min-w-0 self-start py-2 text-center break-words whitespace-normal"
                    >
                        <Link
                            :href="StockProductController.create(producer.id)"
                            :aria-label="
                                trans('Add stock product for :name', {
                                    name: producer.name,
                                })
                            "
                        >
                            {{ trans('Add stock product') }}
                        </Link>
                    </Button>
                </div>

                <p
                    v-if="producer.accountEmail !== null"
                    class="text-sm"
                    :data-test="`account-${producer.id}`"
                >
                    {{
                        trans('Account: :email', {
                            email: producer.accountEmail,
                        })
                    }}
                    <span class="text-muted-foreground">
                        ({{
                            producer.accountInvitationPending
                                ? trans('invitation pending')
                                : trans('active')
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
                    <div class="grid gap-2">
                        <Label :for="`account-name-${producer.id}`">
                            {{ trans('Account name') }}
                        </Label>
                        <Input
                            :id="`account-name-${producer.id}`"
                            name="name"
                            required
                        />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="`account-email-${producer.id}`">
                            {{ trans('Account email') }}
                        </Label>
                        <Input
                            :id="`account-email-${producer.id}`"
                            name="email"
                            type="email"
                            required
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
                        {{ trans('Invite account') }}
                    </Button>
                </Form>
            </li>
        </ul>
    </div>
</template>
