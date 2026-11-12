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
        <div class="welcome-contact-inner">
            <div>
                <h2 id="contact-title">
                    {{ trans('Would you like to meet us?') }}
                </h2>
                <p>
                    {{ trans('We will listen and tell you how to take part.') }}
                </p>
            </div>
            <div class="welcome-contact-details">
                <a class="welcome-contact-button" :href="`mailto:${email}`">
                    {{ trans('Let’s talk') }}
                </a>
                <a class="welcome-email" :href="`mailto:${email}`">{{
                    email
                }}</a>
                <p>{{ trans('Manresa') }}</p>
            </div>
        </div>
    </section>
</template>

<style scoped>
.welcome-contact {
    --welcome-focus: var(--welcome-feature-cream);
    color: var(--welcome-feature-cream);
    background: var(--welcome-feature-surface);
}

.welcome-contact-inner {
    display: grid;
    gap: 1.5rem;
    width: min(100% - 2.5rem, 72rem);
    margin-inline: auto;
    padding-block: clamp(1.5rem, 4vw, 2rem);
}

.welcome-contact h2 {
    margin: 0;
    font-size: clamp(1.65rem, 3.2vw, 2.25rem);
    font-weight: 900;
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
    background: var(--welcome-feature-cream);
    padding: 0.625rem 1.25rem;
    color: #173e53;
    font-weight: 750;
    text-decoration: none;
}

.welcome-contact-button:hover {
    background: #fff9eb;
}

.welcome-email {
    display: inline-flex;
    min-height: 2.75rem;
    align-items: center;
    max-width: 100%;
    overflow-wrap: anywhere;
    color: var(--welcome-feature-cream);
    text-underline-offset: 0.2em;
}

.welcome-contact :is(a:focus-visible) {
    outline: 3px solid var(--welcome-focus);
    outline-offset: 3px;
}

@media (min-width: 48rem) {
    .welcome-contact-inner {
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 20rem), 1fr));
        align-items: center;
        gap: 2.5rem;
        width: min(100% - 4rem, 72rem);
    }
}

@media (max-width: 25rem) {
    .welcome-contact-inner {
        width: min(100% - 2rem, 72rem);
    }
}
</style>
