import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

interface PortalFilters {
    search: string;
    status?: string;
    view?: string;
    invitation_state?: string;
}

export function usePortalFilters(options: { url: string; filters: () => PortalFilters; onSuccess?: () => void }) {
    const search = ref(options.filters().search);
    const status = ref(options.filters().status ?? 'all');
    const invitationState = ref(options.filters().invitation_state ?? 'active');
    const busy = ref(false);
    const pending = ref(false);
    const error = ref('');
    const filtering = computed(() => busy.value || pending.value);
    let timer: ReturnType<typeof setTimeout> | undefined;
    let cancelToken: { cancel: () => void } | null = null;
    let sequence = 0;
    let synchronizing = false;

    function cancel(): void {
        clearTimeout(timer);
        pending.value = false;
        sequence++;
        cancelToken?.cancel();
        cancelToken = null;
        busy.value = false;
    }

    function visit(view = options.filters().view, page = 1): void {
        cancel();
        const current = sequence;
        error.value = '';
        const query = {
            search: search.value.trim(),
            page,
            ...(options.filters().status !== undefined ? { status: status.value } : {}),
            ...(options.filters().invitation_state !== undefined ? { invitation_state: invitationState.value } : {}),
            ...(view !== undefined ? { view } : {}),
        };
        router.get(options.url, query, {
            preserveState: true,
            preserveScroll: true,
            onCancelToken: (token) => {
                cancelToken = token;
            },
            onStart: () => {
                busy.value = true;
            },
            onFinish: () => {
                if (sequence === current) {
                    busy.value = false;
                    cancelToken = null;
                }
            },
            onError: () => {
                if (sequence === current) error.value = 'These results could not be loaded. Check the filters and try again.';
            },
            onSuccess: () => {
                if (sequence === current) options.onSuccess?.();
            },
        });
    }

    watch(
        search,
        () => {
            if (synchronizing) return;
            cancel();
            pending.value = true;
            timer = setTimeout(() => visit(), 350);
        },
        { flush: 'sync' },
    );
    watch(
        status,
        () => {
            if (!synchronizing) visit();
        },
        { flush: 'sync' },
    );
    watch(
        invitationState,
        () => {
            if (!synchronizing && options.filters().invitation_state !== undefined) visit();
        },
        { flush: 'sync' },
    );
    watch(
        options.filters,
        (filters) => {
            clearTimeout(timer);
            pending.value = false;
            synchronizing = true;
            search.value = filters.search;
            status.value = filters.status ?? 'all';
            invitationState.value = filters.invitation_state ?? 'active';
            synchronizing = false;
        },
        { deep: true, flush: 'sync' },
    );

    function clear(): void {
        synchronizing = true;
        search.value = '';
        status.value = 'all';
        invitationState.value = 'active';
        synchronizing = false;
        visit();
    }
    const removeExceptionListener = router.on('exception', (event) => {
        if (!busy.value) return;
        event.preventDefault();
        cancel();
        error.value = 'These results could not be loaded. Please try again.';
    });
    window.addEventListener('popstate', cancel);
    onBeforeUnmount(() => {
        cancel();
        window.removeEventListener('popstate', cancel);
        removeExceptionListener();
    });
    return { search, status, invitationState, busy, filtering, error, visit, clear, cancel };
}
