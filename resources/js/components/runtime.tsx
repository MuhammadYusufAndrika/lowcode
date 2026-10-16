import { date, Empty, Pagination, Panel } from '@/components/management';
import { Button } from '@/components/ui/button';
import { control } from '@/components/workflow-fields';
import { Paginator } from '@/types/management';
import { RuntimeInstance, RuntimeTask } from '@/types/runtime';
import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

const labels: Record<string, string> = {
    running: 'Berjalan',
    completed: 'Selesai',
    rejected: 'Ditolak',
    failed: 'Gagal',
    cancelled: 'Dibatalkan',
    pending: 'Menunggu',
    approved: 'Disetujui',
};
export function Status({ value }: { value: string }) {
    return (
        <span
            className={`inline-block rounded-full px-3 py-1 text-xs font-medium ${['completed', 'approved'].includes(value) ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' : ['failed', 'rejected'].includes(value) ? 'bg-rose-500/10 text-rose-700 dark:text-rose-400' : 'bg-blue-500/10 text-blue-700 dark:text-blue-400'}`}
        >
            {labels[value] ?? value}
        </span>
    );
}
export function StatusFilter({ url, value, statuses }: { url: string; value: string; statuses: string[] }) {
    return (
        <label className="flex max-w-sm items-center gap-3 text-sm">
            Status
            <select
                className={control}
                value={value}
                onChange={(e) => router.get(url, { status: e.target.value }, { preserveState: true, replace: true })}
            >
                {statuses.map((s) => (
                    <option key={s} value={s}>
                        {labels[s] ?? 'Semua status'}
                    </option>
                ))}
            </select>
        </label>
    );
}
export function InstanceTable({ instances }: { instances: Paginator<RuntimeInstance> }) {
    return (
        <>
            <Panel>
                {instances.data.length ? (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="text-muted-foreground border-b">
                                <tr>
                                    {['Pengajuan', 'Pemohon', 'Status', 'Tanggal', ''].map((h, i) => (
                                        <th className="p-3" key={i}>
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {instances.data.map((i) => (
                                    <tr className="border-b last:border-0" key={i.id}>
                                        <td className="p-3">
                                            <p className="font-semibold">{i.workflow.name}</p>
                                            <small className="text-muted-foreground">
                                                #{i.id} · {i.workflow.code} · v{i.version}
                                            </small>
                                        </td>
                                        <td className="p-3">{i.requester.name}</td>
                                        <td className="p-3">
                                            <Status value={i.status} />
                                        </td>
                                        <td className="p-3 whitespace-nowrap">{date(i.created_at)}</td>
                                        <td className="p-3">
                                            <Button asChild variant="outline" size="sm">
                                                <Link href={`/requests/${i.id}`}>Lihat detail</Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <Empty>Belum ada pengajuan yang sesuai.</Empty>
                )}
            </Panel>
            <Pagination data={instances} />
        </>
    );
}
export function Decision({ task }: { task: RuntimeTask }) {
    const [choice, setChoice] = useState<'approved' | 'rejected' | null>(null);
    const form = useForm({ decision: 'approved', comment: '' });
    if (!task.can_decide) return null;
    return (
        <div className="mt-4 grid gap-3 rounded-lg border p-4">
            <label>
                Komentar
                <textarea className={control} maxLength={5000} value={form.data.comment} onChange={(e) => form.setData('comment', e.target.value)} />
            </label>
            {!choice ? (
                <div className="flex gap-2">
                    <Button onClick={() => setChoice('approved')}>Setujui</Button>
                    <Button variant="destructive" onClick={() => setChoice('rejected')}>
                        Tolak
                    </Button>
                </div>
            ) : (
                <div className="grid gap-3">
                    <p>Konfirmasi: {choice === 'approved' ? 'setujui' : 'tolak'} pengajuan ini?</p>
                    <div className="flex gap-2">
                        <Button
                            disabled={form.processing}
                            onClick={() => {
                                form.transform((data) => ({ ...data, decision: choice }));
                                form.post(`/approvals/${task.id}/decision`);
                            }}
                        >
                            {form.processing ? 'Memproses…' : 'Ya, konfirmasi'}
                        </Button>
                        <Button disabled={form.processing} variant="outline" onClick={() => setChoice(null)}>
                            Kembali
                        </Button>
                    </div>
                </div>
            )}
            {Object.values(form.errors).map((error, i) => (
                <p role="alert" className="text-destructive" key={i}>
                    {error}
                </p>
            ))}
        </div>
    );
}
export function Metrics({ stats }: { stats: Record<string, number> }) {
    return (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {Object.entries(stats).map(([key, value]) => (
                <div key={key} className="bg-card rounded-xl border p-5">
                    <p className="text-muted-foreground text-sm">
                        {labels[key] ??
                            {
                                pending_tasks: 'Tugas menunggu',
                                average_completion_hours: 'Rata-rata penyelesaian (jam)',
                                overdue_tasks: 'Tugas terlambat',
                            }[key] ??
                            key}
                    </p>
                    <p className="mt-3 text-3xl font-semibold tabular-nums">{Number(value).toLocaleString('id-ID')}</p>
                </div>
            ))}
        </div>
    );
}
export function StatsChart({
    data,
    nameKey = 'name',
    valueKey = 'value',
}: {
    data: Record<string, string | number>[];
    nameKey?: string;
    valueKey?: string;
}) {
    return (
        <div className="h-64 w-full" role="img" aria-label="Grafik statistik pengajuan">
            <ResponsiveContainer width="100%" height="100%">
                <BarChart data={data}>
                    <CartesianGrid strokeDasharray="3 3" vertical={false} />
                    <XAxis dataKey={nameKey} tick={{ fontSize: 11 }} />
                    <YAxis allowDecimals={false} />
                    <Tooltip />
                    <Bar dataKey={valueKey} name="Jumlah" fill="#6366f1" radius={[6, 6, 0, 0]} />
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}
