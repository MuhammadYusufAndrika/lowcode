import { ManagementPage, Panel } from '@/components/management';
import { Button } from '@/components/ui/button';
import { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Archive, Building2, FilePenLine, GitBranch, Users } from 'lucide-react';

export default function Dashboard({
    stats,
}: {
    stats: { workflows: { total: number; draft: number; archived: number }; users?: number; departments?: number };
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
            <Panel>
                <h2 className="text-lg font-semibold">Fondasi proses organisasi</h2>
                <p className="text-muted-foreground mt-2 max-w-2xl text-sm leading-6">
                    Kelola metadata workflow, pengguna, peran, dan departemen dari satu ruang kerja. Eksekusi proses, pengajuan, dan persetujuan belum
                    tersedia pada tahap ini.
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
