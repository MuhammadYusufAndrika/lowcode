import { Empty, ManagementPage, Pagination, Panel } from '@/components/management';
import { Metrics, StatsChart } from '@/components/runtime';
import { Paginator } from '@/types/management';
interface Audit {
    id: number;
    action: string;
    target_type: string;
    target_id: number;
    created_at: string;
    actor: { id: number; name: string } | null;
    metadata: unknown;
}
interface ReportStats {
    running: number;
    completed: number;
    rejected: number;
    failed: number;
    cancelled: number;
    average_completion_hours: number;
    overdue_tasks: number;
    bottlenecks: { node_name: string; pending: number }[];
}
export default function Reports({ stats, audit_logs }: { stats: ReportStats; audit_logs: Paginator<Audit> }) {
    const { bottlenecks, ...metrics } = stats;
    return (
        <ManagementPage title="Laporan & audit" description="Ringkasan performa proses, hambatan persetujuan, dan jejak audit organisasi.">
            <Metrics stats={metrics} />
            <div className="grid gap-6 lg:grid-cols-2">
                <Panel>
                    <h2 className="mb-5 font-semibold">Distribusi pengajuan</h2>
                    <StatsChart
                        data={['running', 'completed', 'rejected', 'failed', 'cancelled'].map((name) => ({
                            name,
                            value: stats[name as keyof Omit<ReportStats, 'bottlenecks'>],
                        }))}
                    />
                </Panel>
                <Panel>
                    <h2 className="mb-5 font-semibold">Hambatan persetujuan</h2>
                    {bottlenecks.length ? (
                        <ul className="grid gap-4">
                            {bottlenecks.map((b) => (
                                <li className="flex justify-between border-b pb-3" key={b.node_name}>
                                    <span>{b.node_name}</span>
                                    <strong>{b.pending} menunggu</strong>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <Empty>Tidak ada tugas tertunda.</Empty>
                    )}
                </Panel>
            </div>
            <Panel>
                <h2 className="mb-5 font-semibold">Jejak audit</h2>
                <div className="overflow-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr>
                                {['Waktu', 'Pelaku', 'Aktivitas', 'Target'].map((h) => (
                                    <th className="border-b p-3" key={h}>
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {audit_logs.data.map((l) => (
                                <tr className="border-b last:border-0" key={l.id}>
                                    <td className="p-3 whitespace-nowrap">{new Date(l.created_at).toLocaleString('id-ID')}</td>
                                    <td className="p-3">{l.actor?.name ?? 'System'}</td>
                                    <td className="p-3">
                                        {l.action}
                                        <details className="text-muted-foreground mt-1 text-xs">
                                            <summary>Metadata</summary>
                                            <pre className="whitespace-pre-wrap">{JSON.stringify(l.metadata, null, 2)}</pre>
                                        </details>
                                    </td>
                                    <td className="p-3">
                                        {l.target_type} #{l.target_id}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {!audit_logs.data.length && <Empty>Belum ada aktivitas audit.</Empty>}
            </Panel>
            <Pagination data={audit_logs} />
        </ManagementPage>
    );
}
