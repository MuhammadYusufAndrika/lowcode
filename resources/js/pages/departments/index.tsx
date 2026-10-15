import { ConfirmAction, Empty, Field, ManagementPage, Pagination, Panel } from '@/components/management';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { SharedData } from '@/types';
import { Department, Paginator } from '@/types/management';
import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

function DepartmentForm({ department }: { department?: Department }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ name: department?.name ?? '' });
    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                setOpen(value);
                if (value) {
                    form.reset();
                    form.clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button variant={department ? 'outline' : 'default'} size={department ? 'sm' : 'default'}>
                    {department ? 'Edit' : 'Tambah departemen'}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>{department ? 'Edit departemen' : 'Tambah departemen'}</DialogTitle>
                <DialogDescription>Gunakan nama departemen yang jelas dan unik.</DialogDescription>
                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
                        if (department) form.put(`/departments/${department.id}`, options);
                        else form.post('/departments', options);
                    }}
                >
                    <Field name="department-name" label="Nama departemen" error={form.errors.name}>
                        <Input id="department-name" required value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    </Field>
                    <Button disabled={form.processing}>{form.processing ? 'Menyimpan…' : 'Simpan departemen'}</Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
export default function Departments({ departments }: { departments: Paginator<Department> }) {
    const allowed = usePage<SharedData>().props.auth.permissions.includes('departments.manage');
    return (
        <ManagementPage title="Departemen" description="Susun struktur organisasi dan penempatan pengguna." action={allowed && <DepartmentForm />}>
            <Panel>
                {departments.data.length === 0 ? (
                    <Empty>Belum ada departemen. Tambahkan departemen pertama organisasi Anda.</Empty>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="text-muted-foreground border-b">
                                <tr>
                                    <th className="px-3 py-3 font-medium">Departemen</th>
                                    <th className="px-3 py-3 font-medium">Jumlah pengguna</th>
                                    <th className="px-3 py-3 font-medium">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {departments.data.map((d) => (
                                    <tr key={d.id} className="border-b last:border-0">
                                        <td className="px-3 py-5 font-semibold">{d.name}</td>
                                        <td className="px-3 py-5">{d.users_count} pengguna</td>
                                        <td className="px-3 py-5">
                                            {allowed && (
                                                <div className="flex gap-2">
                                                    <DepartmentForm department={d} />
                                                    <ConfirmAction url={`/departments/${d.id}`} label="Hapus" name={d.name} />
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Panel>
            <Pagination data={departments} />
        </ManagementPage>
    );
}
