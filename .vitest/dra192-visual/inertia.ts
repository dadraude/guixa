import { defineComponent, h, reactive } from 'vue';

export const page = reactive({
    component: 'Dashboard',
    url: '/dashboard',
    props: {
        sidebarOpen: true,
        errors: {} as Record<string, string>,
        roleAreas: [{ role: 'member', label: 'Member', href: '/cycles' }],
        auth: {
            user: {
                id: 1,
                name: 'Maria Riera',
                email: 'maria@example.test',
                email_verified_at: null,
            },
        },
    },
});

export const usePage = () => page;
export const Head = defineComponent({ setup: () => () => null });
export const Form = defineComponent({
    inheritAttrs: false,
    setup(_, { attrs, slots }) {
        const query = new URLSearchParams(location.search);
        return () =>
            h(
                'form',
                {
                    ...attrs,
                    onSubmit: (event: Event) => event.preventDefault(),
                },
                slots.default?.({
                    errors:
                        query.get('error') === '1'
                            ? { domain: 'The value is invalid.' }
                            : {},
                    processing: query.get('processing') === '1',
                }),
            );
    },
});
export const Link = defineComponent({
    inheritAttrs: false,
    setup(_, { attrs, slots }) {
        return () =>
            h(
                'a',
                {
                    ...attrs,
                    href:
                        typeof attrs.href === 'string'
                            ? attrs.href
                            : typeof attrs.href === 'object' &&
                                attrs.href !== null &&
                                'url' in attrs.href
                              ? attrs.href.url
                              : '#',
                },
                slots.default?.(),
            );
    },
});
export const router = {
    put(url: string, data: unknown, options: Record<string, () => void>) {
        window.dispatchEvent(
            new CustomEvent('fixture-request', {
                detail: { method: 'PUT', url, data },
            }),
        );
        options.onStart?.();
        if (new URLSearchParams(location.search).get('hold') !== '1')
            options.onFinish?.();
    },
    delete(url: string, options: Record<string, () => void>) {
        window.dispatchEvent(
            new CustomEvent('fixture-request', {
                detail: { method: 'DELETE', url },
            }),
        );
        options.onStart?.();
        options.onFinish?.();
    },
    flushAll() {},
};
