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
    wedding_time: string | null;
    ceremony_venue: string | null;
    reception_venue: string | null;
    public_slug: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
