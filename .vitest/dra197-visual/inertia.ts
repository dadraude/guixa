import { defineComponent, h, reactive } from 'vue';

const query = new URLSearchParams(location.search);

export const page = reactive({
    component: 'auth/Login',
    url: '/login',
    props: {
        sidebarOpen: true,
        errors: {} as Record<string, string>,
        roleAreas: [] as Array<{ role: string; label: string; href: string }>,
        mustVerifyEmail: false,
        status: undefined as string | undefined,
        auth: {
            user: null as null | Record<string, unknown>,
        },
    },
});
export const layoutProps = reactive<Record<string, string>>({});

export const usePage = () => page;
export const setLayoutProps = (props: Record<string, string>) =>
    Object.assign(layoutProps, props);
export const Head = defineComponent({ setup: () => () => null });

const errorMessage = 'El valor no és vàlid.';
const errorFields = [
    'email',
    'password',
    'password_confirmation',
    'current_password',
    'code',
    'recovery_code',
    'name',
    'domain',
    'cutoff',
    'quantity',
];
const fieldErrors = () =>
    query.get('error') === '1'
        ? Object.fromEntries(errorFields.map((field) => [field, errorMessage]))
        : {};

const emitRequest = (method: string, url: unknown, data?: unknown) =>
    window.dispatchEvent(
        new CustomEvent('fixture-request', { detail: { method, url, data } }),
    );

export const Form = defineComponent({
    inheritAttrs: false,
    setup(_, { attrs, slots }) {
        return () =>
            h(
                'form',
                {
                    ...attrs,
                    onSubmit: (event: Event) => {
                        event.preventDefault();
                        emitRequest(
                            String(attrs.method ?? 'post'),
                            attrs.action,
                            Object.fromEntries(
                                new FormData(event.target as HTMLFormElement),
                            ),
                        );
                        (attrs.onSuccess as (() => void) | undefined)?.();
                    },
                },
                slots.default?.({
                    errors: fieldErrors(),
                    processing: query.get('processing') === '1',
                    clearErrors() {},
                    reset() {},
                }),
            );
    },
});
export const Link = defineComponent({
    inheritAttrs: false,
    setup(_, { attrs, slots }) {
        return () =>
            h(
                attrs.as === 'button' ? 'button' : 'a',
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
    post(url: string, data: unknown, options: Record<string, () => void> = {}) {
        emitRequest('POST', url, data);
        options.onStart?.();
        options.onFinish?.();
    },
    put(url: string, data: unknown, options: Record<string, () => void> = {}) {
        emitRequest('PUT', url, data);
        options.onStart?.();
        options.onFinish?.();
    },
    delete(url: string, options: Record<string, () => void> = {}) {
        emitRequest('DELETE', url);
        options.onStart?.();
        options.onFinish?.();
    },
    visit(url: string) {
        emitRequest('GET', url);
    },
    reload() {},
    flushAll() {},
};
export const useHttp = () => ({
    async submit(route: { url: string }) {
        if (route.url.includes('qr-code'))
            return {
                svg: '<svg xmlns="http://www.w3.org/2000/svg" version="1.1" viewBox="0 0 21 21" width="192" height="192" shape-rendering="crispEdges"><path d="M0 0h7v7H0zM14 0h7v7h-7zM0 14h7v7H0zM9 9h3v3H9zM9 15h5v3H9z" fill="#000"/></svg>',
                url: 'otpauth://totp/example',
            };
        if (route.url.includes('secret-key'))
            return { secretKey: 'JBSWY3DPEHPK3PXP' };
        return ['aaaaa-bbbbb', 'ccccc-ddddd', 'eeeee-fffff', 'ggggg-hhhhh'];
    },
});
