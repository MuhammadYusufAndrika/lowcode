export interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}
export interface Workflow {
    id: number;
    name: string;
    code: string;
    description: string | null;
    category: string | null;
    status: 'draft' | 'archived';
    owner: { id: number; name: string };
    created_at: string;
    updated_at: string;
    published_version?: number | null;
    can: { builder: boolean; update: boolean; delete: boolean; duplicate: boolean };
}
export interface Department {
    id: number;
    name: string;
    users_count: number;
    created_at: string;
    updated_at: string;
}
export interface ManagedUser {
    id: number;
    name: string;
    email: string;
    department_id: number | null;
    department: { id: number; name: string } | null;
    roles: string[];
    created_at: string;
    updated_at: string;
    can: { update: boolean; delete: boolean };
}
