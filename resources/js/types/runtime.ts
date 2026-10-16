import { Scalar } from '@/lib/workflow';
export interface RuntimeInstance {
    id: number;
    status: string;
    current_node_id: string | null;
    created_at: string;
    completed_at: string | null;
    workflow: { id: number; name: string; code: string };
    requester: { id: number; name: string };
    version: number;
    data?: Record<string, Scalar | { file_id: number; name: string }>;
    tasks?: RuntimeTask[];
    logs?: { id: number; action: string; node_id: string | null; metadata: unknown; created_at: string; actor_name: string }[];
    files?: { id: number; field_key: string; original_name: string; mime: string; size: number; download_url: string }[];
}
export interface RuntimeTask {
    id: number;
    node_id: string;
    node_name: string;
    status: string;
    due_at: string | null;
    comment: string | null;
    decided_at: string | null;
    assignee_name: string;
    can_decide: boolean;
    instance?: RuntimeInstance;
}
export interface CatalogWorkflow {
    id: number;
    name: string;
    code: string;
    description: string | null;
    category: string | null;
    version?: number;
    version_id?: number;
}
