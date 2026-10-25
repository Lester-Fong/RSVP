import type { InvitationLinkStatus } from '@/types';

/** Personal RSVP contracts. HTTP access requires the server-controlled personal mode. */
export type RsvpStatus = 'pending' | 'attending' | 'declined';
export type PersonalFailureKind = 'stale_response' | 'stale_access' | 'unavailable' | 'session' | 'rate_limited' | 'validation' | 'network';
export type PersonalResult<T> = { ok: true; value: T } | { ok: false; kind: PersonalFailureKind; retryAfter?: number };

export interface RsvpPerson {
    id: number;
    name: string;
}

/** Owner-only aggregate of scoped RsvpDelegationManager::inspect results; no secrets/contacts. */
export interface OwnerRsvpMember {
    groupId: number;
    guest: RsvpPerson;
    access_version: number;
    link_version: number;
    link_status: InvitationLinkStatus;
    invitation_state?: 'active' | 'withdrawn';
    mode: 'independent' | 'represented';
    representative: RsvpPerson | null;
    dependents: RsvpPerson[];
}
export interface OwnerRsvpGroup {
    id: number;
    label: string;
    members: OwnerRsvpMember[];
}
export interface CredentialCommand {
    guestId: number;
    action: 'generate' | 'copy' | 'revoke' | 'regenerate';
    expectedVersion: number;
    expectedAccessVersion: number;
}
export interface DelegationCommand {
    dependentId: number;
    representativeId: number | null;
    expectedAccessVersions: Record<number, number>;
    expectedDependentLinkVersion: number;
}
export interface InvitationLifecycleConfirmation {
    guest: RsvpPerson;
    state: 'active' | 'withdrawn';
    withdrawnAt: string | null;
    status: RsvpStatus;
    respondedAt: string | null;
    action: 'withdraw' | 'reinstate';
    affected: RsvpPerson[];
    confirmation: {
        expectedState: 'active' | 'withdrawn';
        expectedGuests: { guestId: number; accessVersion: number; responseVersion: number; linkVersion: number }[];
    };
}
export interface InvitationLifecycleTransport {
    inspect(action: 'withdraw' | 'reinstate', signal: AbortSignal): Promise<PersonalResult<{ confirmation: InvitationLifecycleConfirmation }>>;
    change(
        action: 'withdraw' | 'reinstate',
        confirmation: InvitationLifecycleConfirmation['confirmation'],
        signal: AbortSignal,
    ): Promise<PersonalResult<{ result: { state: 'active' | 'withdrawn'; changed: boolean } }>>;
}
export interface OwnerRsvpTransport {
    /** Explicit owner action only. Adapter builds a same-origin URL, never returns raw service tokens. */
    credential(command: CredentialCommand, signal: AbortSignal): Promise<PersonalResult<{ group: OwnerRsvpGroup; url: string | null }>>;
    delegation(command: DelegationCommand, signal: AbortSignal): Promise<PersonalResult<{ group: OwnerRsvpGroup }>>;
    refresh(signal: AbortSignal): Promise<PersonalResult<{ group: OwnerRsvpGroup }>>;
}

/** Exact allowlisted PersonalRsvp presentation; never augment with the household roster. */
export interface PersonalRsvpState {
    greeting: { name: string };
    access_version: number;
    guests: {
        reference: string;
        name: string;
        version: number;
        represented: boolean;
        status: RsvpStatus;
        respondedAt: string | null;
    }[];
}
export interface PersonalRsvpSubmission {
    access_version: number;
    responses: { guest: string; version: number; status: Exclude<RsvpStatus, 'pending'> }[];
}
export interface PersonalRsvpTransport {
    submit(input: PersonalRsvpSubmission, signal: AbortSignal): Promise<PersonalResult<PersonalRsvpState> & { receipt?: { changed: boolean } }>;
    reload(signal: AbortSignal): Promise<PersonalResult<PersonalRsvpState>>;
}
