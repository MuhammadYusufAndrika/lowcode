export type Scalar = string | number | boolean | null;
export type FieldType = 'text' | 'textarea' | 'number' | 'date' | 'select' | 'radio' | 'checkbox' | 'file';
export interface FormField {
    key: string;
    label: string;
    type: FieldType;
    required: boolean;
    placeholder?: string | null;
    default_value?: Scalar;
    options?: string[];
    min?: number;
    max?: number;
}
export type NodeType = 'start' | 'form' | 'approval' | 'condition' | 'notification' | 'end';
export type Branch = 'next' | 'approved' | 'rejected' | 'true' | 'false';
export interface NodeData extends Record<string, unknown> {
    label: string;
    fields?: FormField[];
    assignee_type?: 'user' | 'role';
    assignee_id?: number | string | null;
    deadline_hours?: number | null;
    escalation_user_id?: number | null;
    condition?: { field: string; operator: 'eq' | 'neq' | 'gt' | 'gte' | 'lt' | 'lte' | 'in'; value: Scalar | Scalar[] };
    message?: string;
    outcome?: 'completed' | 'rejected';
}
export interface GraphNode {
    id: string;
    type: NodeType;
    position: { x: number; y: number };
    data: NodeData;
}
export interface GraphEdge {
    id: string;
    source: string;
    target: string;
    sourceHandle: Branch;
}
export interface Definition {
    nodes: GraphNode[];
    edges: GraphEdge[];
}
export const nodeLabels: Record<NodeType, string> = {
    start: 'Mulai',
    form: 'Formulir',
    approval: 'Persetujuan',
    condition: 'Kondisi',
    notification: 'Notifikasi',
    end: 'Selesai',
};
export function branches(type: NodeType): Branch[] {
    return type === 'end' ? [] : type === 'approval' ? ['approved', 'rejected'] : type === 'condition' ? ['true', 'false'] : ['next'];
}
export function nodeData(type: NodeType): NodeData {
    return {
        label: nodeLabels[type],
        ...(type === 'form' ? { fields: [] } : {}),
        ...(type === 'approval' ? { assignee_type: 'user' as const, assignee_id: null } : {}),
        ...(type === 'condition' ? { condition: { field: '', operator: 'eq' as const, value: '' } } : {}),
        ...(type === 'notification' ? { message: '' } : {}),
        ...(type === 'end' ? { outcome: 'completed' as const } : {}),
    };
}
export function canConnect(graph: Definition, source: string, target: string, handle: string | null | undefined): boolean {
    const from = graph.nodes.find((n) => n.id === source);
    const to = graph.nodes.find((n) => n.id === target);
    if (
        !from ||
        !to ||
        source === target ||
        to.type === 'start' ||
        !branches(from.type).includes(handle as Branch) ||
        graph.edges.some((e) => e.source === source && (e.sourceHandle ?? 'next') === handle)
    )
        return false;
    const visited = new Set<string>();
    const queue = [target];
    while (queue.length) {
        const id = queue.pop()!;
        if (id === source) return false;
        if (visited.has(id)) continue;
        visited.add(id);
        graph.edges.filter((e) => e.source === id).forEach((e) => queue.push(e.target));
    }
    return true;
}
export function fieldValue(field: FormField, text: string): Scalar {
    if (field.type === 'file') return null;
    if (field.type === 'checkbox') return text === 'true';
    if (field.type === 'number') {
        if (!text.trim() || !Number.isFinite(Number(text))) throw new Error('Masukkan angka yang valid.');
        return Number(text);
    }
    return text;
}
export function defaults(fields: FormField[]): Record<string, Scalar> {
    return Object.fromEntries(
        fields.map((f) => [f.key, f.type === 'file' ? null : (f.default_value ?? (f.type === 'checkbox' ? false : f.type === 'number' ? null : ''))]),
    );
}
export function fieldErrors(fields: FormField[]): string[] {
    const seen = new Set<string>();
    return fields.flatMap((f) => {
        const errors: string[] = [];
        if (!/^[a-zA-Z][a-zA-Z0-9_]*$/.test(f.key) || seen.has(f.key)) errors.push(`Kunci tidak valid atau duplikat: ${f.key}`);
        seen.add(f.key);
        if (!f.label.trim()) errors.push(`Label wajib: ${f.key}`);
        if (f.min !== undefined && f.max !== undefined && f.min > f.max) errors.push(`Min melebihi max: ${f.key}`);
        if (['select', 'radio'].includes(f.type) && !f.options?.length) errors.push(`Opsi wajib: ${f.key}`);
        return errors;
    });
}
export async function definitionRequest(
    url: string,
    method: string,
    body: unknown,
): Promise<{ revision?: number; version?: number; message?: string; valid?: boolean; errors?: string[] }> {
    const token = document.cookie
        .split('; ')
        .find((c) => c.startsWith('XSRF-TOKEN='))
        ?.slice(11);
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {}),
        },
        body: JSON.stringify(body),
    });
    const result: { message?: string; errors?: Record<string, string[]> } = await response.json();
    if (!response.ok)
        throw new Error(
            response.status === 409
                ? 'Konflik revisi: draf telah diubah pengguna lain. Salin perubahan Anda sebelum memuat ulang halaman.'
                : Object.values(result.errors ?? {})
                      .flat()
                      .join('\n') ||
                  result.message ||
                  'Permintaan gagal.',
        );
    return result as { revision?: number; version?: number; message?: string; valid?: boolean; errors?: string[] };
}
