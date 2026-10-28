import type {
    InvitationLifecycleTransport,
    OwnerRsvpTransport,
    PersonalFailureKind,
    PersonalResult,
    PersonalRsvpState,
    PersonalRsvpTransport,
} from '@/types/personal-rsvp';

const kinds: PersonalFailureKind[] = ['stale_response', 'stale_access', 'unavailable', 'session', 'rate_limited', 'validation', 'network'];
async function request<T>(path: string, signal: AbortSignal, body?: unknown, csrfToken?: string): Promise<PersonalResult<T>> {
    const endpoint = new URL(path, window.location.origin);
    if (endpoint.origin !== window.location.origin) return { ok: false, kind: 'unavailable' };
    const controller = new AbortController();
    const abort = () => controller.abort();
    signal.addEventListener('abort', abort, { once: true });
    if (signal.aborted) controller.abort();
    const timeout = setTimeout(abort, 15000);
    try {
        const cookie = document.cookie.split('; ').find((value) => value.startsWith('XSRF-TOKEN='));
        const response = await fetch(endpoint, {
            method: body === undefined ? 'GET' : 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            redirect: 'error',
            signal: controller.signal,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(body === undefined
                    ? {}
                    : {
                          'Content-Type': 'application/json',
                          ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : { 'X-XSRF-TOKEN': cookie ? decodeURIComponent(cookie.slice(11)) : '' }),
                      }),
            },
            ...(body === undefined ? {} : { body: JSON.stringify(body) }),
        });
        const data = await response.json().catch(() => null);
        if (!response.ok) {
            const fallback: PersonalFailureKind =
                response.status === 404 || response.status === 403
                    ? 'unavailable'
                    : response.status === 401 || response.status === 419
                      ? 'session'
                      : response.status === 429
                        ? 'rate_limited'
                        : response.status === 422
                          ? 'validation'
                          : 'network';
            return {
                ok: false,
                kind: kinds.includes(data?.kind) ? data.kind : fallback,
                retryAfter: Number(response.headers.get('Retry-After') || data?.retryAfter || 60),
            };
        }
        return data ? { ok: true, value: data as T } : { ok: false, kind: 'network' };
    } catch {
        return { ok: false, kind: 'network' };
    } finally {
        clearTimeout(timeout);
        signal.removeEventListener('abort', abort);
    }
}

export function ownerRsvpTransport(account: number, wedding: number, invitationGroup: number): OwnerRsvpTransport {
    const parameters = { account, wedding, invitationGroup };
    return {
        refresh: (signal) => request(route('accounts.weddings.rsvp-access.show', parameters, false), signal),
        credential: (command, signal) =>
            request(
                route('accounts.weddings.rsvp-access.credential', { ...parameters, guest: command.guestId, action: command.action }, false),
                signal,
                {
                    expectedVersion: command.expectedVersion,
                    expectedAccessVersion: command.expectedAccessVersion,
                },
            ),
        delegation: (command, signal) =>
            request(route('accounts.weddings.rsvp-access.delegation', { ...parameters, guest: command.dependentId }, false), signal, {
                representativeId: command.representativeId,
                expectedAccessVersions: command.expectedAccessVersions,
                expectedDependentLinkVersion: command.expectedDependentLinkVersion,
            }),
    };
}

export function invitationLifecycleTransport(account: number, wedding: number, invitationGroup: number, guest: number): InvitationLifecycleTransport {
    const parameters = { account, wedding, invitationGroup, guest };
    return {
        inspect: (action, signal) => request(route('accounts.weddings.rsvp-access.invitation.inspect', { ...parameters, action }, false), signal),
        change: (action, confirmation, signal) =>
            request(route('accounts.weddings.rsvp-access.invitation.change', { ...parameters, action }, false), signal, confirmation),
    };
}

function isPersonalState(value: unknown): value is PersonalRsvpState {
    if (!value || typeof value !== 'object') return false;
    const state = value as PersonalRsvpState;
    return (
        typeof state.greeting?.name === 'string' &&
        Number.isSafeInteger(state.access_version) &&
        state.access_version >= 0 &&
        Array.isArray(state.guests) &&
        state.guests.length > 0 &&
        state.guests.every(
            (guest) =>
                guest &&
                typeof guest.reference === 'string' &&
                /^[a-f0-9]{64}$/.test(guest.reference) &&
                typeof guest.name === 'string' &&
                typeof guest.represented === 'boolean' &&
                Number.isSafeInteger(guest.version) &&
                guest.version >= 0 &&
                ['pending', 'attending', 'declined'].includes(guest.status) &&
                (guest.respondedAt === null || (typeof guest.respondedAt === 'string' && Number.isFinite(Date.parse(guest.respondedAt)))),
        )
    );
}

export function personalRsvpTransport(csrfUrl: string): PersonalRsvpTransport {
    const path = window.location.pathname.replace(/\/$/, '');
    return {
        reload: async (signal) => {
            const result = await request<{ rsvp: PersonalRsvpState }>(path, signal);
            if (!result.ok) return result;
            return isPersonalState(result.value?.rsvp) ? { ok: true, value: result.value.rsvp } : { ok: false, kind: 'network' };
        },
        submit: async (input, signal) => {
            const setup = await request<{ csrfToken: string }>(csrfUrl, signal);
            if (!setup.ok) return setup;
            if (typeof setup.value.csrfToken !== 'string' || !setup.value.csrfToken) return { ok: false, kind: 'session' };
            const result = await request<{ rsvp: PersonalRsvpState; receipt?: { changed: boolean } }>(
                path + '/responses',
                signal,
                input,
                setup.value.csrfToken,
            );
            if (!result.ok) return result;
            if (!isPersonalState(result.value?.rsvp)) return { ok: false, kind: 'network' };
            return {
                ok: true,
                value: result.value.rsvp,
                ...(typeof result.value.receipt?.changed === 'boolean' ? { receipt: { changed: result.value.receipt.changed } } : {}),
            };
        },
    };
}
