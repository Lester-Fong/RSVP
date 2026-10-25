import type { PageProps } from '@inertiajs/core';
import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User | null;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface SharedData extends PageProps {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface AccountSummary {
    id: number;
    name: string;
}

export interface WeddingSummary {
    id: number;
    partner_one_name: string;
    partner_two_name: string;
    wedding_date: string | null;
    status: string;
}

export interface WeddingDetails extends WeddingSummary {
    timezone?: string;
    wedding_time: string | null;
    ceremony_venue: string | null;
    reception_venue: string | null;
    public_slug: string;
}

export type WeddingPresentation = Pick<
    WeddingDetails,
    'partner_one_name' | 'partner_two_name' | 'wedding_date' | 'wedding_time' | 'ceremony_venue' | 'reception_venue'
>;

export interface InvitationCustomization {
    introduction_message: string;
    closing_message: string;
    sections: {
        ceremony: boolean;
        reception: boolean;
        closing: boolean;
    };
}

export interface InvitationHeroPhoto {
    url: string;
}

export interface WeddingPublicationSummary {
    cmsV2?: boolean;
    published: boolean;
    draftSaved: boolean;
    supportedDraft: boolean;
    hasChanges: boolean;
    draftVersion: number;
    publicationVersion: number;
    snapshotToken: string;
    publicUrl: string;
    publishedAt: string | null;
}

export type BreadcrumbItemType = BreadcrumbItem;
export interface GuestSummary {
    id: number;
    name: string;
    has_response: boolean;
    invitation_state?: 'active' | 'withdrawn';
    rsvp_status?: 'pending' | 'attending' | 'declined';
}

export interface InvitationGroupSummary {
    id: number;
    label: string;
    contact_email: string | null;
    contact_phone: string | null;
    guests_count: number;
    guests: GuestSummary[];
    link_status: InvitationLinkStatus;
    link_version: number;
}

export type InvitationLinkStatus = 'not_generated' | 'active' | 'revoked';

export interface AttendanceSummary {
    invited: number;
    attending: number;
    declined: number;
    pending: number;
}
export interface AttendanceGuest {
    id: number;
    name: string;
    group: string;
    status: 'pending' | 'attending' | 'declined';
    respondedAt: string | null;
}
export interface AttendanceGroup extends AttendanceSummary {
    id: number;
    label: string;
    matching: number;
}
export interface AttendancePage<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
}
export interface AttendanceFilters {
    search: string;
    status: 'all' | AttendanceGuest['status'];
    view: 'guests' | 'groups';
}

export interface InvitationRsvpState {
    version: number;
    guests: {
        reference: string;
        name: string;
        status: 'pending' | 'attending' | 'declined';
        respondedAt: string | null;
    }[];
}
