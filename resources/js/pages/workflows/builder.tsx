import { ManagementPage } from '@/components/management';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { control, FieldEditor } from '@/components/workflow-fields';
import {
    Branch,
    branches,
    canConnect,
    Definition,
    definitionRequest,
    fieldErrors,
    fieldValue,
    NodeData,
    nodeData,
    nodeLabels,
    NodeType,
    Scalar,
} from '@/lib/workflow';
import { Link, router } from '@inertiajs/react';
import {
    Background,
    Controls,
    Handle,
    MiniMap,
    Node,
    NodeProps,
    Position,
    ReactFlow,
    ReactFlowProvider,
    useEdgesState,
    useNodesState,
    useReactFlow,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';
import { useEffect, useState } from 'react';

type FlowNode = Node<NodeData, NodeType>;
const colors: Record<NodeType, string> = {
    start: '#10b981',
    form: '#3b82f6',
    approval: '#8b5cf6',
    condition: '#f59e0b',
    notification: '#06b6d4',
    end: '#f43f5e',
};
function WorkflowNode({ data, type, selected }: NodeProps<FlowNode>) {
    const outputs = branches(type ?? 'form');
    return (
        <div
            className={`bg-card min-w-48 rounded-xl border-2 p-4 shadow-sm ${selected ? 'ring-primary/20 ring-4' : ''}`}
            style={{ borderColor: colors[type ?? 'form'] }}
        >
            {type !== 'start' && <Handle type="target" position={Position.Top} />}
            <p className="text-xs font-semibold tracking-wider uppercase" style={{ color: colors[type ?? 'form'] }}>
                {nodeLabels[type ?? 'form']}
            </p>
            <p className="mt-2 max-w-56 truncate font-semibold">{data.label}</p>
            {type === 'form' && <p className="text-muted-foreground mt-1 text-xs">{data.fields?.length ?? 0} kolom</p>}
            <div className="text-muted-foreground mt-4 flex justify-around gap-6 text-[10px]">
                {outputs.map((handle, i) => (
                    <div key={handle}>
                        {handle}
                        <Handle
                            type="source"
                            position={Position.Bottom}
                            id={handle}
                            style={{ left: `${((i + 1) * 100) / (outputs.length + 1)}%`, background: colors[type ?? 'form'] }}
                        />
                    </div>
                ))}
            </div>
        </div>
    );
}
const nodeTypes = {
    start: WorkflowNode,
    form: WorkflowNode,
    approval: WorkflowNode,
    condition: WorkflowNode,
    notification: WorkflowNode,
    end: WorkflowNode,
};
interface Props {
    workflow: { id: number; name: string; code: string; draft_revision: number; status: string };
    definition: Definition;
    versions: { id: number; version: number; created_at: string }[];
    users: { id: number; name: string }[];
    roles: { id: number; name: string }[];
}
function Builder({ workflow, definition, versions, users, roles }: Props) {
    const [nodes, setNodes, onNodesChange] = useNodesState<FlowNode>(definition.nodes);
    const [edges, setEdges, onEdgesChange] = useEdgesState(definition.edges);
    const [selectedId, select] = useState<string>();
    const [revision, setRevision] = useState(workflow.draft_revision);
    const [saved, setSaved] = useState(JSON.stringify(definition));
    const [history, setHistory] = useState(versions);
    const [pending, setPending] = useState(false);
    const [notice, setNotice] = useState('');
    const [error, setError] = useState('');
    const [target, setTarget] = useState('');
    const [handle, setHandle] = useState<Branch>('next');
    const { screenToFlowPosition } = useReactFlow();
    const graph: Definition = {
        nodes: nodes.map((n) => ({ id: n.id, type: n.type ?? 'form', position: n.position, data: n.data })),
        edges: edges.map((e) => ({ id: e.id, source: e.source, target: e.target, sourceHandle: e.sourceHandle ?? 'next' })),
    };
    const serialized = JSON.stringify(graph);
    const dirty = serialized !== saved;
    const selected = nodes.find((n) => n.id === selectedId);
    const fields = nodes.flatMap((n) => n.data.fields ?? []);
    useEffect(() => {
        const unload = (e: BeforeUnloadEvent) => {
            if (dirty) e.preventDefault();
        };
        window.addEventListener('beforeunload', unload);
        const off = router.on('before', () => {
            if (dirty && !window.confirm('Ada perubahan belum disimpan. Tinggalkan builder?')) return false;
        });
        return () => {
            window.removeEventListener('beforeunload', unload);
            off();
        };
    }, [dirty]);
    function add(type: NodeType, position = { x: 120 + (nodes.length % 3) * 250, y: 80 + Math.floor(nodes.length / 3) * 180 }) {
        const id = crypto.randomUUID();
        setNodes((ns) => [...ns.map((n) => ({ ...n, selected: false })), { id, type, position, data: nodeData(type), selected: true }]);
        select(id);
    }
    function patch(data: Partial<NodeData>) {
        setNodes((ns) => ns.map((n) => (n.id === selectedId ? { ...n, data: { ...n.data, ...data } } : n)));
    }
    function connect(source: string, destination: string, branch: string | null | undefined) {
        if (!canConnect(graph, source, destination, branch)) {
            setError('Koneksi ditolak: periksa siklus, node awal/akhir, dan cabang yang sudah dipakai.');
            return;
        }
        setEdges((es) => [...es, { id: crypto.randomUUID(), source, target: destination, sourceHandle: branch as Branch }]);
        setError('');
    }
    async function persist(action: 'definition' | 'validate' | 'publish') {
        setPending(true);
        setError('');
        setNotice('');
        try {
            if (action !== 'definition') {
                const local = fieldErrors(fields);
                if (local.length) throw new Error(local.join('\n'));
            }
            const result = await definitionRequest(`/workflows/${workflow.id}/${action}`, action === 'definition' ? 'PUT' : 'POST', {
                definition: graph,
                revision,
            });
            if (result.valid === false) setError(result.errors?.join('\n') ?? 'Definisi belum valid.');
            else {
                setNotice(action === 'validate' ? 'Definisi valid dan siap diterbitkan.' : (result.message ?? 'Perubahan berhasil disimpan.'));
                if (result.revision !== undefined) {
                    setRevision(result.revision);
                    setSaved(serialized);
                }
                if (result.version !== undefined)
                    setHistory((h) => [{ id: Date.now(), version: result.version!, created_at: new Date().toISOString() }, ...h]);
            }
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Koneksi gagal. Coba lagi.');
        } finally {
            setPending(false);
        }
    }
    return (
        <ManagementPage
            title={workflow.name}
            description={`${workflow.code} · Builder visual · Revisi ${revision} · ${dirty ? 'Belum disimpan' : 'Tersimpan'}`}
            action={
                <div className="flex flex-wrap gap-2">
                    <Button asChild variant="outline">
                        <Link href={`/workflows/${workflow.id}/edit`}>Metadata</Link>
                    </Button>
                    <Button disabled={pending} variant="outline" onClick={() => persist('validate')}>
                        Validasi
                    </Button>
                    <Button disabled={pending} variant="secondary" onClick={() => persist('definition')}>
                        {pending ? 'Memproses…' : 'Simpan draf'}
                    </Button>
                    <Button
                        disabled={pending || workflow.status === 'archived'}
                        onClick={() => {
                            if (window.confirm('Terbitkan versi baru untuk pengajuan?')) void persist('publish');
                        }}
                    >
                        Terbitkan
                    </Button>
                </div>
            }
        >
            <div aria-live="polite">
                {notice && <p className="rounded-lg bg-emerald-500/10 p-3 text-sm">{notice}</p>}
                {error && (
                    <p role="alert" className="bg-destructive/10 text-destructive rounded-lg p-3 text-sm whitespace-pre-wrap">
                        {error}
                    </p>
                )}
            </div>
            <div className="grid items-start gap-4 xl:grid-cols-[180px_minmax(0,1fr)_300px]">
                <aside className="bg-card grid gap-3 rounded-xl border p-4">
                    <h2 className="font-semibold">Palet node</h2>
                    <p className="text-muted-foreground text-xs">Seret ke kanvas atau tekan tombol tambah.</p>
                    {(Object.keys(nodeLabels) as NodeType[]).map((type) => (
                        <Button
                            key={type}
                            variant="outline"
                            draggable
                            onDragStart={(e) => {
                                e.dataTransfer.setData('application/processflow', type);
                                e.dataTransfer.effectAllowed = 'move';
                            }}
                            onClick={() => add(type)}
                            className="justify-start"
                        >
                            <span className="mr-2 h-2 w-2 rounded-full" style={{ background: colors[type] }} />+ {nodeLabels[type]}
                        </Button>
                    ))}
                    <details className="mt-3 text-sm">
                        <summary className="cursor-pointer font-medium">Riwayat versi ({history.length})</summary>
                        <ul className="mt-3 grid gap-3">
                            {history.map((v) => (
                                <li key={v.id}>
                                    Versi {v.version}
                                    <span className="text-muted-foreground block text-xs">{new Date(v.created_at).toLocaleString('id-ID')}</span>
                                </li>
                            ))}
                            {!history.length && <li>Belum diterbitkan.</li>}
                        </ul>
                    </details>
                </aside>
                <section aria-label="Kanvas workflow" className="h-[620px] min-w-0 overflow-hidden rounded-xl border bg-slate-50 dark:bg-slate-950">
                    <ReactFlow
                        nodes={nodes}
                        edges={edges}
                        nodeTypes={nodeTypes}
                        onNodesChange={onNodesChange}
                        onEdgesChange={onEdgesChange}
                        onNodeClick={(_, n) => {
                            select(n.id);
                            setHandle(branches(n.type ?? 'form')[0] ?? 'next');
                        }}
                        onSelectionChange={({ nodes: selection }) => {
                            if (selection[0]) select(selection[0].id);
                        }}
                        onConnect={(c) => connect(c.source, c.target, c.sourceHandle)}
                        isValidConnection={(c) => canConnect(graph, c.source, c.target, c.sourceHandle)}
                        onDragOver={(e) => {
                            e.preventDefault();
                            e.dataTransfer.dropEffect = 'move';
                        }}
                        onDrop={(e) => {
                            e.preventDefault();
                            const type = e.dataTransfer.getData('application/processflow') as NodeType;
                            if (type in nodeLabels) add(type, screenToFlowPosition({ x: e.clientX, y: e.clientY }));
                        }}
                        fitView
                        deleteKeyCode={['Backspace', 'Delete']}
                    >
                        <Background gap={20} />
                        <Controls />
                        <MiniMap nodeColor={(n) => colors[n.type as NodeType] ?? '#64748b'} pannable zoomable />
                    </ReactFlow>
                </section>
                <aside className="bg-card max-h-[800px] overflow-y-auto rounded-xl border p-4 text-sm">
                    <h2 className="mb-4 font-semibold">Properti node</h2>
                    {selected ? (
                        <div className="grid gap-4" key={selected.id}>
                            <label>
                                Label
                                <Input value={selected.data.label} onChange={(e) => patch({ label: e.target.value })} />
                            </label>
                            {selected.type === 'form' && (
                                <FieldEditor fields={selected.data.fields ?? []} onChange={(value) => patch({ fields: value })} />
                            )}
                            {selected.type === 'approval' && (
                                <>
                                    <label>
                                        Penugasan
                                        <select
                                            className={control}
                                            value={selected.data.assignee_type ?? 'user'}
                                            onChange={(e) => patch({ assignee_type: e.target.value as 'user' | 'role', assignee_id: null })}
                                        >
                                            <option value="user">Pengguna</option>
                                            <option value="role">Peran</option>
                                        </select>
                                    </label>
                                    <label>
                                        Penerima
                                        <select
                                            className={control}
                                            value={selected.data.assignee_id ?? ''}
                                            onChange={(e) => patch({ assignee_id: e.target.value ? Number(e.target.value) : null })}
                                        >
                                            <option value="">Pilih penerima</option>
                                            {(selected.data.assignee_type === 'role' ? roles : users).map((u) => (
                                                <option key={u.id} value={u.id}>
                                                    {u.name}
                                                </option>
                                            ))}
                                        </select>
                                    </label>
                                    <label>
                                        Batas waktu (jam)
                                        <Input
                                            type="number"
                                            min="0.01"
                                            max="8760"
                                            step="0.01"
                                            value={selected.data.deadline_hours ?? ''}
                                            onChange={(e) => patch({ deadline_hours: e.target.value ? Number(e.target.value) : null })}
                                        />
                                    </label>
                                    <label>
                                        Eskalasi ke
                                        <select
                                            className={control}
                                            value={selected.data.escalation_user_id ?? ''}
                                            onChange={(e) => patch({ escalation_user_id: e.target.value ? Number(e.target.value) : null })}
                                        >
                                            <option value="">Tanpa eskalasi</option>
                                            {users.map((u) => (
                                                <option key={u.id} value={u.id}>
                                                    {u.name}
                                                </option>
                                            ))}
                                        </select>
                                    </label>
                                </>
                            )}
                            {selected.type === 'condition' && (
                                <>
                                    <label>
                                        Kolom
                                        <select
                                            className={control}
                                            value={selected.data.condition?.field ?? ''}
                                            onChange={(e) => patch({ condition: { field: e.target.value, operator: 'eq', value: '' } })}
                                        >
                                            <option value="">Pilih kolom</option>
                                            {fields
                                                .filter((f) => f.type !== 'file')
                                                .map((f) => (
                                                    <option key={f.key} value={f.key}>
                                                        {f.label} ({f.key})
                                                    </option>
                                                ))}
                                        </select>
                                    </label>
                                    <label>
                                        Operator
                                        <select
                                            className={control}
                                            value={selected.data.condition?.operator ?? 'eq'}
                                            onChange={(e) =>
                                                patch({
                                                    condition: {
                                                        field: selected.data.condition?.field ?? '',
                                                        value: '',
                                                        operator: e.target.value as NonNullable<NodeData['condition']>['operator'],
                                                    },
                                                })
                                            }
                                        >
                                            {['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'in'].map((op) => (
                                                <option key={op}>{op}</option>
                                            ))}
                                        </select>
                                    </label>
                                    <label>
                                        Nilai {selected.data.condition?.operator === 'in' ? '(daftar JSON)' : '(sesuai tipe kolom)'}
                                        <Input
                                            key={`${selected.data.condition?.field}-${selected.data.condition?.operator}`}
                                            defaultValue={
                                                Array.isArray(selected.data.condition?.value)
                                                    ? JSON.stringify(selected.data.condition.value)
                                                    : String(selected.data.condition?.value ?? '')
                                            }
                                            onBlur={(e) => {
                                                try {
                                                    const c = selected.data.condition!;
                                                    const f = fields.find((f) => f.key === c.field);
                                                    if (!f) throw new Error('Pilih kolom terlebih dahulu.');
                                                    let value: Scalar | Scalar[];
                                                    if (c.operator === 'in') {
                                                        const parsed: unknown = JSON.parse(e.target.value);
                                                        if (
                                                            !Array.isArray(parsed) ||
                                                            parsed.some((v) => v !== null && !['string', 'number', 'boolean'].includes(typeof v))
                                                        )
                                                            throw new Error('Gunakan daftar JSON skalar.');
                                                        value = parsed as Scalar[];
                                                    } else value = fieldValue(f, e.target.value);
                                                    patch({ condition: { ...c, value } });
                                                    setError('');
                                                } catch (e) {
                                                    setError(e instanceof Error ? e.message : 'Nilai tidak valid');
                                                }
                                            }}
                                        />
                                    </label>
                                </>
                            )}
                            {selected.type === 'notification' && (
                                <label>
                                    Pesan
                                    <textarea
                                        className={control}
                                        rows={5}
                                        maxLength={5000}
                                        value={selected.data.message ?? ''}
                                        onChange={(e) => patch({ message: e.target.value })}
                                    />
                                </label>
                            )}
                            {selected.type === 'end' && (
                                <label>
                                    Hasil akhir
                                    <select
                                        className={control}
                                        value={selected.data.outcome ?? 'completed'}
                                        onChange={(e) => patch({ outcome: e.target.value as 'completed' | 'rejected' })}
                                    >
                                        <option value="completed">Selesai</option>
                                        <option value="rejected">Ditolak</option>
                                    </select>
                                </label>
                            )}
                            <div className="grid gap-2 border-t pt-4">
                                <h3 className="font-semibold">Koneksi tanpa drag</h3>
                                <label>
                                    Cabang
                                    <select className={control} value={handle} onChange={(e) => setHandle(e.target.value as Branch)}>
                                        {branches(selected.type ?? 'form').map((b) => (
                                            <option key={b}>{b}</option>
                                        ))}
                                    </select>
                                </label>
                                <label>
                                    Tujuan
                                    <select className={control} value={target} onChange={(e) => setTarget(e.target.value)}>
                                        <option value="">Pilih node</option>
                                        {nodes
                                            .filter((n) => n.id !== selected.id)
                                            .map((n) => (
                                                <option key={n.id} value={n.id}>
                                                    {n.data.label}
                                                </option>
                                            ))}
                                    </select>
                                </label>
                                <Button
                                    variant="outline"
                                    disabled={!target || selected.type === 'end'}
                                    onClick={() => connect(selected.id, target, handle)}
                                >
                                    Hubungkan
                                </Button>
                                {edges
                                    .filter((e) => e.source === selected.id || e.target === selected.id)
                                    .map((e) => (
                                        <div className="flex items-center justify-between gap-2" key={e.id}>
                                            <span className="text-xs">
                                                {nodes.find((n) => n.id === e.source)?.data.label} →{' '}
                                                {nodes.find((n) => n.id === e.target)?.data.label} ({e.sourceHandle})
                                            </span>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                aria-label="Hapus koneksi"
                                                onClick={() => setEdges((es) => es.filter((item) => item.id !== e.id))}
                                            >
                                                ×
                                            </Button>
                                        </div>
                                    ))}
                            </div>
                            <Button
                                variant="destructive"
                                onClick={() => {
                                    setNodes((ns) => ns.filter((n) => n.id !== selected.id));
                                    setEdges((es) => es.filter((e) => e.source !== selected.id && e.target !== selected.id));
                                    select(undefined);
                                }}
                            >
                                Hapus node
                            </Button>
                        </div>
                    ) : (
                        <p className="text-muted-foreground">
                            Pilih node untuk mengatur properti. Gunakan Tab untuk navigasi kanvas, panah untuk posisi, Delete untuk menghapus.
                        </p>
                    )}
                </aside>
            </div>
        </ManagementPage>
    );
}
export default function WorkflowBuilder(props: Props) {
    return (
        <ReactFlowProvider>
            <Builder {...props} />
        </ReactFlowProvider>
    );
}
