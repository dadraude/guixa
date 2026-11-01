<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: trans('Profile'),
        href: editProfile(),
    },
    {
        title: trans('Security'),
        href: editSecurity(),
    },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="pilot-settings">
        <div class="pilot-page pilot-settings-inner">
            <Heading
                as="div"
                :title="trans('Settings')"
                :description="trans('Manage your profile and account settings')"
            />

            <div class="pilot-settings-body">
                <aside class="pilot-settings-nav pilot-panel p-4 sm:p-6">
                    <h2 class="mb-2 text-lg font-semibold">
                        {{ trans('Account settings') }}
                    </h2>
                    <p class="mb-4 text-sm text-muted-foreground">
                        {{ trans('Profile and security') }}
                    </p>
                    <nav
                        class="flex flex-col space-y-1 space-x-0"
                        :aria-label="trans('Settings')"
                    >
                        <Button
                            v-for="item in sidebarNavItems"
                            :key="toUrl(item.href)"
                            variant="ghost"
                            :class="[
                                'w-full justify-start',
                                {
                                    'bg-secondary font-semibold text-secondary-foreground':
                                        isCurrentOrParentUrl(item.href),
                                },
                            ]"
                            as-child
                        >
                            <Link
                                :href="item.href"
                                :aria-current="
                                    isCurrentOrParentUrl(item.href)
                                        ? 'page'
                                        : undefined
                                "
                            >
                                <component :is="item.icon" class="h-4 w-4" />
                                {{ trans(item.title) }}
                            </Link>
                        </Button>
                    </nav>
                </aside>

                <div class="pilot-settings-main min-w-0">
                    <section class="min-w-0 space-y-6">
                        <slot />
                    </section>
                </div>
            </div>
        </div>
    </div>
</template>
