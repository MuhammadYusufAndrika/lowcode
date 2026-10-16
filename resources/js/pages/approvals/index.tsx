import { Empty, ManagementPage, Pagination, Panel } from '@/components/management';
import { Decision, Status, StatusFilter } from '@/components/runtime';
import { Paginator } from '@/types/management';
import { RuntimeTask } from '@/types/runtime';
import { Link } from '@inertiajs/react';
export default function Approvals({ tasks, filters }: { tasks: Paginator<RuntimeTask>; filters: { status: string } }) {
    return (
        <ManagementPage title="Kotak persetujuan" description="Tinjau pengajuan yang ditugaskan kepada Anda atau peran Anda.">
            <StatusFilter url="/approvals" value={filters.status} statuses={['pending', 'approved', 'rejected', 'cancelled']} />
            <div className="grid gap-5 lg:grid-cols-2">
                {tasks.data.map((t) => (
                    <Panel key={t.id}>
                        <div className="flex justify-between gap-4">
                            <h2 className="font-semibold">{t.node_name}</h2>
                            <Status value={t.status} />
                        </div>
                        {t.instance && (
                            <Link className="text-primary mt-3 block underline" href={`/requests/${t.instance.id}`}>
                                #{t.instance.id} · {t.instance.workflow.name}
                            </Link>
                        )}
                        <p className="text-muted-foreground mt-2 text-sm">
                            Pemohon: {t.instance?.requester.name} · Penerima: {t.assignee_name}
                        </p>
                        {t.due_at && (
                            <p
                                className={`mt-2 text-sm ${t.status === 'pending' && new Date(t.due_at) < new Date() ? 'text-destructive' : 'text-muted-foreground'}`}
                            >
                                Tenggat: {new Date(t.due_at).toLocaleString('id-ID')}
                            </p>
                        )}
                        {t.comment && <p className="mt-3 text-sm whitespace-pre-wrap">{t.comment}</p>}
                        <Decision task={t} />
                    </Panel>
                ))}
            </div>
            {!tasks.data.length && <Empty>Tidak ada tugas pada status ini.</Empty>}
            <Pagination data={tasks} />
        </ManagementPage>
    );
}
