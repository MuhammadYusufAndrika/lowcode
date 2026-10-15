import { LucideIcon } from 'lucide-react';

export interface Auth<Authenticated extends boolean = true> {
    user: Authenticated extends true ? User : User | null;
    permissions: string[];
    roles: string[];
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData<Authenticated extends boolean = true> {
    flash: { success?: string | null };
    name: string;
    quote: { message: string; author: string };
    auth: Auth<Authenticated>;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}
