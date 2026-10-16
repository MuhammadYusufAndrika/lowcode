import { date, Empty, ManagementPage, Panel } from '@/components/management';
import { Decision, Status } from '@/components/runtime';
import { Button } from '@/components/ui/button';
import { SharedData } from '@/types';
import { RuntimeInstance } from '@/types/runtime';
import { useForm, usePage } from '@inertiajs/react';
export default function RequestDetail({ instance }: { instance: RuntimeInstance }) {
    const { auth } = usePage<SharedData>().props;
    const form = useForm({});
    return (
        <ManagementPage
            title={`Pengajuan #${instance.id}`}
            description={`${instance.workflow.name} · ${instance.workflow.code} · Versi ${instance.version}`}
            action={
                instance.status === 'running' &&
                auth.user.id === instance.requester.id && (
                    <Button
                        disabled={form.processing}
                        variant="destructive"
                        onClick={() => {
                            if (window.confirm('Batalkan pengajuan ini? Tugas aktif akan dihentikan.')) form.post(`/requests/${instance.id}/cancel`);
                        }}
                    >
                        Batalkan pengajuan
                    </Button>
                )
            }
        >
            <Panel>
                <div className="flex flex-wrap items-center gap-4">
                    <Status value={instance.status} />
                    <p className="text-sm">
                        {instance.requester.name} · {date(instance.created_at)}
                    </p>
                    {instance.completed_at && <p className="text-muted-foreground text-sm">Berakhir {date(instance.completed_at)}</p>}
                </div>
            </Panel>
            <div className="grid items-start gap-6 lg:grid-cols-2">
                <div className="grid gap-6">
                    <Panel>
                        <h2 className="mb-5 font-semibold">Data pengajuan</h2>
                        <dl className="grid gap-4">
                            {Object.entries(instance.data ?? {}).map(([key, value]) => (
                                <div key={key} className="border-b pb-3 last:border-0">
                                    <dt className="text-muted-foreground text-xs">{key}</dt>
                                    <dd className="mt-1 text-sm break-words whitespace-pre-wrap">
                                        {typeof value === 'boolean'
                                            ? value
                                                ? 'Ya'
                                                : 'Tidak'
                                            : value !== null && typeof value === 'object'
                                              ? JSON.stringify(value)
                                              : String(value ?? '—')}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                        {!Object.keys(instance.data ?? {}).length && <Empty>Tidak ada data tambahan.</Empty>}
                    </Panel>
                    <Panel>
                        <h2 className="mb-4 font-semibold">Lampiran</h2>
                        {instance.files?.length ? (
                            <ul className="grid gap-3">
                                {instance.files.map((f) => (
                                    <li key={f.id}>
                                        <a className="text-primary underline" href={f.download_url}>
                                            {f.original_name}
                                        </a>
                                        <p className="text-muted-foreground text-xs">
                                            {f.field_key} · {(f.size / 1024).toFixed(1)} KB · {f.mime}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-muted-foreground text-sm">Tidak ada lampiran.</p>
                        )}
                    </Panel>
                </div>
                <div className="grid gap-6">
                    <Panel>
                        <h2 className="mb-4 font-semibold">Tugas persetujuan</h2>
                        {instance.tasks?.map((t) => (
                            <article key={t.id} className="mb-4 rounded-xl border p-4">
                                <div className="flex flex-wrap justify-between gap-3">
                                    <h3 className="font-medium">{t.node_name}</h3>
                                    <Status value={t.status} />
                                </div>
                                <p className="text-muted-foreground mt-2 text-sm">
                                    {t.assignee_name}
                                    {t.due_at && ` · Tenggat ${new Date(t.due_at).toLocaleString('id-ID')}`}
                                </p>
                                {t.comment && <p className="mt-3 text-sm whitespace-pre-wrap">{t.comment}</p>}
                                <Decision task={t} />
                            </article>
                        ))}
                        {!instance.tasks?.length && <p className="text-muted-foreground text-sm">Belum ada tugas.</p>}
                    </Panel>
                    <Panel>
                        <h2 className="mb-5 font-semibold">Linimasa aktivitas</h2>
                        <ol className="ml-2 grid gap-5 border-l pl-5">
                            {instance.logs?.map((l) => (
                                <li key={l.id}>
                                    <p className="text-sm font-medium">{l.action}</p>
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        {l.actor_name} · {new Date(l.created_at).toLocaleString('id-ID')}
                                        {l.node_id && ` · ${l.node_id}`}
                                    </p>
                                    {l.metadata !== null && (
                                        <details className="mt-2 text-xs">
                                            <summary>Detail aktivitas</summary>
                                            <pre className="mt-2 overflow-auto whitespace-pre-wrap">{JSON.stringify(l.metadata, null, 2)}</pre>
                                        </details>
                                    )}
                                </li>
                            ))}
                        </ol>
                    </Panel>
                </div>
            </div>
        </ManagementPage>
    );
}
