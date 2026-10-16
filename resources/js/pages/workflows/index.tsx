import { ConfirmAction, date, Empty, Field, ManagementPage, Pagination, Panel, Search } from '@/components/management';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Paginator, Workflow } from '@/types/management';
import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

function Duplicate({ workflow }: { workflow: Workflow }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ name: `${workflow.name} (salinan)`, code: `${workflow.code}-copy` });
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Duplikat
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Duplikat workflow</DialogTitle>
                <DialogDescription>Buat draf baru dengan metadata dari {workflow.name}.</DialogDescription>
                <form
                    className="grid gap-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(`/workflows/${workflow.id}/duplicate`, { onSuccess: () => setOpen(false) });
                    }}
                >
                    <Field name="duplicate-name" label="Nama salinan" error={form.errors.name}>
                        <Input id="duplicate-name" required value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    </Field>
                    <Field name="duplicate-code" label="Kode unik salinan" error={form.errors.code}>
                        <Input id="duplicate-code" required value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} />
                    </Field>
                    <Button disabled={form.processing}>{form.processing ? 'Memproses…' : 'Buat salinan'}</Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
export default function Workflows({
    workflows,
    filters,
    can,
}: {
    workflows: Paginator<Workflow>;
    filters: { search: string; status: string };
    can: { create: boolean };
}) {
    return (
        <ManagementPage
            title="Workflow"
            description="Kelola definisi dan metadata proses organisasi."
            action={
                can.create && (
                    <Button asChild>
                        <Link href="/workflows/create">Buat workflow</Link>
                    </Button>
                )
            }
        >
            <Panel>
                <Search url="/workflows" search={filters.search ?? ''} status={filters.status ?? ''} />
                <div className="mt-6 overflow-x-auto">
                    {workflows.data.length === 0 ? (
                        <Empty>Belum ada workflow yang sesuai. Coba ubah pencarian atau buat workflow baru.</Empty>
                    ) : (
                        <table className="w-full text-left text-sm">
                            <thead className="text-muted-foreground border-b">
                                <tr>
                                    {['Workflow', 'Status', 'Pemilik', 'Diperbarui', 'Aksi'].map((h) => (
                                        <th key={h} className="px-3 py-3 font-medium">
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {workflows.data.map((w) => (
                                    <tr key={w.id} className="border-b last:border-0">
                                        <td className="min-w-56 px-3 py-5">
                                            <p className="font-semibold">{w.name}</p>
                                            <p className="text-muted-foreground mt-1 text-xs">
                                                {w.code} · {w.category || 'Tanpa kategori'}
                                            </p>
                                            {w.description && <p className="text-muted-foreground mt-2 line-clamp-2 max-w-sm">{w.description}</p>}
                                        </td>
                                        <td className="px-3 py-5">
                                            <span
                                                className={`rounded-full px-3 py-1 text-xs font-medium ${w.status === 'draft' ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400' : 'bg-muted text-muted-foreground'}`}
                                            >
                                                {w.status === 'draft' ? 'Draf' : 'Diarsipkan'}
                                            </span>
                                        </td>
                                        <td className="px-3 py-5">{w.owner.name}</td>
                                        <td className="text-muted-foreground px-3 py-5 whitespace-nowrap">{date(w.updated_at)}</td>
                                        <td className="px-3 py-5">
                                            <div className="flex flex-wrap gap-2">
                                                {w.can.builder && (
                                                    <Button asChild size="sm">
                                                        <Link href={`/workflows/${w.id}/builder`}>Buka Builder</Link>
                                                    </Button>
                                                )}
                                                {w.can.update && (
                                                    <Button asChild size="sm" variant="outline">
                                                        <Link href={`/workflows/${w.id}/edit`}>Edit</Link>
                                                    </Button>
                                                )}
                                                {w.can.duplicate && <Duplicate workflow={w} />}
                                                {w.can.update && w.status === 'draft' && (
                                                    <ConfirmAction method="post" url={`/workflows/${w.id}/archive`} label="Arsipkan" name={w.name} />
                                                )}
                                                {w.can.delete && <ConfirmAction url={`/workflows/${w.id}`} label="Hapus" name={w.name} />}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </Panel>
            <Pagination data={workflows} />
        </ManagementPage>
    );
}
