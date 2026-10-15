import { Field, ManagementPage, Panel } from '@/components/management';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Workflow } from '@/types/management';
import { Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function WorkflowForm({ workflow }: { workflow: Workflow | null }) {
    const form = useForm({
        name: workflow?.name ?? '',
        code: workflow?.code ?? '',
        description: workflow?.description ?? '',
        category: workflow?.category ?? '',
    });
    function submit(event: FormEvent) {
        event.preventDefault();
        if (workflow) form.put(`/workflows/${workflow.id}`);
        else form.post('/workflows');
    }
    return (
        <ManagementPage
            title={workflow ? 'Edit workflow' : 'Buat workflow'}
            description="Tetapkan identitas dan tujuan workflow. Workflow baru disimpan sebagai draf."
        >
            <div className="max-w-3xl">
                <Panel>
                    <form onSubmit={submit} className="grid gap-6">
                        <div className="grid gap-6 sm:grid-cols-2">
                            <Field name="name" label="Nama workflow" error={form.errors.name}>
                                <Input id="name" required value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                            </Field>
                            <Field name="code" label="Kode unik" error={form.errors.code}>
                                <Input id="code" required value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} />
                            </Field>
                        </div>
                        <Field name="category" label="Kategori (opsional)" error={form.errors.category}>
                            <Input id="category" value={form.data.category} onChange={(e) => form.setData('category', e.target.value)} />
                        </Field>
                        <Field name="description" label="Deskripsi (opsional)" error={form.errors.description}>
                            <textarea
                                id="description"
                                className="bg-background min-h-32 rounded-md border p-3 text-sm"
                                value={form.data.description}
                                onChange={(e) => form.setData('description', e.target.value)}
                            />
                        </Field>
                        <div className="flex gap-3">
                            <Button disabled={form.processing}>{form.processing ? 'Menyimpan…' : 'Simpan workflow'}</Button>
                            <Button asChild variant="outline">
                                <Link href="/workflows">Batal</Link>
                            </Button>
                        </div>
                    </form>
                </Panel>
            </div>
        </ManagementPage>
    );
}
