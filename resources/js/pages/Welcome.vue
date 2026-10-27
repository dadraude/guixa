<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import WelcomePage from '@/components/WelcomePage.vue';
import { dashboard, login } from '@/routes';
</script>

<template>
    <Head title="Guixa" />
    <WelcomePage
        :authenticated="Boolean($page.props.auth.user)"
        :action-href="$page.props.auth.user ? dashboard().url : login().url"
    />
</template>
