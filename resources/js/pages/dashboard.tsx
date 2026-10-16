import { Empty, ManagementPage, Panel } from '@/components/management';
import { Metrics, StatsChart } from '@/components/runtime';
import { Button } from '@/components/ui/button';
import { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Archive, Building2, FilePenLine, GitBranch, Users } from 'lucide-react';

export default function Dashboard({
    stats,
    recent_logs = [],
    chart = [],
}: {
    stats: { workflows: { total: number; draft: number; archived: number }; users?: number; departments?: number; runtime?: Record<string, number> };
    recent_logs?: { id: number; action: string; node_id: string | null; created_at: string; workflow_instance_id: number; actor_name: string }[];
    chart?: { date: string; count: number }[];
}) {
    const { auth } = usePage<SharedData>().props;
    const cards = [
        { title: 'Total workflow', value: stats.workflows.total, icon: GitBranch },
        { title: 'Workflow draf', value: stats.workflows.draft, icon: FilePenLine },
        { title: 'Diarsipkan', value: stats.workflows.archived, icon: Archive },
    ];
    if (stats.users !== undefined) cards.push({ title: 'Pengguna', value: stats.users, icon: Users });
    if (stats.departments !== undefined) cards.push({ title: 'Departemen', value: stats.departments, icon: Building2 });
    return (
        <ManagementPage title="Dasbor" description={`Selamat datang, ${auth.user.name}. Berikut ringkasan data yang dapat Anda akses.`}>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {cards.map(({ title, value, icon: Icon }) => (
                    <Panel key={title}>
                        <div className="flex items-center justify-between">
                            <p className="text-muted-foreground text-sm">{title}</p>
                            <Icon className="text-muted-foreground size-5" />
                        </div>
                        <p className="mt-5 text-4xl font-semibold tabular-nums">{value.toLocaleString('id-ID')}</p>
                    </Panel>
                ))}
            </div>
            {stats.runtime && <Metrics stats={stats.runtime} />}
            <div className="grid gap-6 lg:grid-cols-2">
                <Panel>
                    <h2 className="mb-5 font-semibold">Pengajuan 7 hari terakhir</h2>
                    <StatsChart data={chart} nameKey="date" valueKey="count" />
                </Panel>
                <Panel>
                    <h2 className="mb-5 font-semibold">Aktivitas terbaru</h2>
                    <ul className="grid gap-4">
                        {recent_logs.map((log) => (
                            <li key={log.id} className="border-b pb-3 text-sm">
                                <p className="font-medium">{log.action}</p>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    {log.actor_name} · #{log.workflow_instance_id} · {new Date(log.created_at).toLocaleString('id-ID')}
                                </p>
                            </li>
                        ))}
                    </ul>
                    {!recent_logs.length && <Empty>Belum ada aktivitas.</Empty>}
                </Panel>
            </div>
            <Panel>
                <h2 className="text-lg font-semibold">Ruang kerja proses organisasi</h2>
                <p className="text-muted-foreground mt-2 max-w-2xl text-sm leading-6">
                    Rancang alur persetujuan, terbitkan layanan, dan pantau pengajuan organisasi dari satu ruang kerja.
                </p>
                <div className="mt-5 flex flex-wrap gap-3">
                    {auth.permissions.some((p) => ['workflows.view.all', 'workflows.view.own'].includes(p)) && (
                        <Button asChild>
                            <Link href="/workflows">Kelola workflow</Link>
                        </Button>
                    )}
                    {auth.permissions.includes('users.manage') && (
                        <Button asChild variant="outline">
                            <Link href="/users">Kelola pengguna</Link>
                        </Button>
                    )}
                </div>
            </Panel>
        </ManagementPage>
    );
}
