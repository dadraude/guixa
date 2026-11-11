<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';

const email = 'laguixamanresa@gmail.com';
</script>

<template>
    <section
        id="contact"
        class="welcome-contact"
        aria-labelledby="contact-title"
    >
        <div>
            <h2 id="contact-title">
                {{ trans('Would you like to meet us?') }}
            </h2>
            <p>{{ trans('We will listen and tell you how to take part.') }}</p>
        </div>
        <div class="welcome-contact-details">
            <a class="welcome-contact-button" :href="`mailto:${email}`">
                {{ trans('Let’s talk') }}
            </a>
            <a class="welcome-email" :href="`mailto:${email}`">{{ email }}</a>
            <p>{{ trans('Manresa') }}</p>
        </div>
    </section>
</template>

<style scoped>
.welcome-contact {
    display: grid;
    gap: 1.5rem;
    padding: clamp(1.5rem, 5vw, 3rem);
    background: var(--welcome-blue-soft);
}

.welcome-contact h2 {
    margin: 0;
    font-size: clamp(1.5rem, 3.2vw, 2rem);
    font-weight: 800;
    letter-spacing: -0.035em;
    line-height: 1.2;
}

.welcome-contact p {
    margin: 0.5rem 0 0;
    line-height: 1.5;
}

.welcome-contact-details {
    display: grid;
    justify-items: start;
    gap: 0.5rem;
}

.welcome-contact-button {
    display: inline-flex;
    min-height: 2.75rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.4rem;
    background: var(--welcome-blue);
    padding: 0.625rem 1.25rem;
    color: #fff;
    font-weight: 750;
    text-decoration: none;
}

.welcome-contact-button:hover {
    background: var(--welcome-blue-hover);
}

:global(.dark) .welcome-contact-button {
    color: #15232a;
}

.welcome-email {
    display: inline-flex;
    min-height: 2.75rem;
    align-items: center;
    max-width: 100%;
    overflow-wrap: anywhere;
    color: var(--welcome-ink);
    text-underline-offset: 0.2em;
}

.welcome-contact :is(a:focus-visible) {
    outline: 3px solid var(--welcome-focus);
    outline-offset: 3px;
}

@media (min-width: 48rem) {
    .welcome-contact {
        grid-template-columns: minmax(0, 1fr) minmax(16rem, 0.75fr);
        align-items: center;
        gap: 2.5rem;
    }
}
</style>
