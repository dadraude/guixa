<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Home, ShieldCheck } from '@lucide/vue';
import { computed } from 'vue';
import ActiveProfileController from '@/actions/App/Http/Controllers/Auth/ActiveProfileController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';
import type { ActingAs } from '@/types';

const page = usePage();

const actingAs = computed(() => page.props.auth.actingAs);
const canSwitch = computed(() => page.props.auth.profiles.length > 1);
const otherProfile = computed<ActingAs>(() =>
    actingAs.value === 'administrator' ? 'member' : 'administrator',
);

const activeLabel = computed(() =>
    actingAs.value === 'administrator'
        ? t('Acting as administrator')
        : t('Acting as member'),
);
const switchLabel = computed(() =>
    otherProfile.value === 'administrator'
        ? t('Switch to administrator')
        : t('Switch to member'),
);

// A member who is nothing else has nothing to tell apart, so only
// administrators and people with both profiles see the badge.
const showsBadge = computed(
    () => actingAs.value === 'administrator' || canSwitch.value,
);
</script>

<template>
    <div v-if="showsBadge" class="ml-auto flex items-center gap-2">
        <Badge
            variant="secondary"
            data-test="acting-as"
            :data-profile="actingAs"
        >
            <ShieldCheck v-if="actingAs === 'administrator'" />
            <Home v-else />
            {{ activeLabel }}
        </Badge>
        <Button v-if="canSwitch" variant="outline" size="sm" as-child>
            <Link
                :href="ActiveProfileController.update.url()"
                method="post"
                :data="{ profile: otherProfile }"
                as="button"
                data-test="switch-profile-button"
            >
                {{ switchLabel }}
            </Link>
        </Button>
    </div>
</template>
