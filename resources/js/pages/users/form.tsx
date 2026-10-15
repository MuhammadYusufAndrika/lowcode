import InputError from '@/components/input-error';
import { Field, ManagementPage, Panel } from '@/components/management';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ManagedUser } from '@/types/management';
import { Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function UserForm({
    user,
    roles,
    departments,
}: {
    user: ManagedUser | null;
    roles: string[];
    departments: { id: number; name: string }[];
}) {
    const form = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        password: '',
        password_confirmation: '',
        department_id: user?.department_id?.toString() ?? '',
        roles: user?.roles ?? ([] as string[]),
    });
    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            department_id: data.department_id || null,
            ...(user && !data.password ? { password: undefined, password_confirmation: undefined } : {}),
        }));
        if (user) form.put(`/users/${user.id}`);
        else form.post('/users');
    }
    return (
        <ManagementPage title={user ? 'Edit pengguna' : 'Tambah pengguna'} description="Atur identitas, departemen, dan peran akses pengguna.">
            <div className="max-w-3xl">
                <Panel>
                    <form onSubmit={submit} className="grid gap-6">
                        <div className="grid gap-6 sm:grid-cols-2">
                            <Field name="name" label="Nama lengkap" error={form.errors.name}>
                                <Input
                                    id="name"
                                    required
                                    autoComplete="name"
                                    value={form.data.name}
                                    onChange={(e) => form.setData('name', e.target.value)}
                                />
                            </Field>
                            <Field name="email" label="Alamat email" error={form.errors.email}>
                                <Input
                                    id="email"
                                    required
                                    type="email"
                                    autoComplete="email"
                                    value={form.data.email}
                                    onChange={(e) => form.setData('email', e.target.value)}
                                />
                            </Field>
                            <Field name="password" label={user ? 'Kata sandi baru (opsional)' : 'Kata sandi'} error={form.errors.password}>
                                <Input
                                    id="password"
                                    type="password"
                                    autoComplete="new-password"
                                    required={!user}
                                    value={form.data.password}
                                    onChange={(e) => form.setData('password', e.target.value)}
                                />
                            </Field>
                            <Field name="password_confirmation" label="Konfirmasi kata sandi" error={form.errors.password_confirmation}>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    autoComplete="new-password"
                                    required={!user || !!form.data.password}
                                    value={form.data.password_confirmation}
                                    onChange={(e) => form.setData('password_confirmation', e.target.value)}
                                />
                            </Field>
                        </div>
                        <Field name="department_id" label="Departemen" error={form.errors.department_id}>
                            <select
                                id="department_id"
                                className="bg-background h-10 rounded-md border px-3 text-sm"
                                value={form.data.department_id}
                                onChange={(e) => form.setData('department_id', e.target.value)}
                            >
                                <option value="">Tanpa departemen</option>
                                {departments.map((d) => (
                                    <option key={d.id} value={d.id}>
                                        {d.name}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <fieldset className="grid gap-3">
                            <legend className="mb-3 text-sm font-medium">Peran pengguna</legend>
                            <p className="text-muted-foreground text-sm">Pilih peran yang sesuai dengan tanggung jawab pengguna.</p>
                            <div className="grid gap-3 sm:grid-cols-2">
                                {roles.map((role) => (
                                    <label key={role} className="flex items-center gap-3 rounded-lg border p-3 text-sm">
                                        <input
                                            type="checkbox"
                                            className="accent-primary size-4"
                                            checked={form.data.roles.includes(role)}
                                            onChange={(e) =>
                                                form.setData(
                                                    'roles',
                                                    e.target.checked ? [...form.data.roles, role] : form.data.roles.filter((r) => r !== role),
                                                )
                                            }
                                        />
                                        {role}
                                    </label>
                                ))}
                            </div>
                            <InputError message={form.errors.roles} />
                        </fieldset>
                        <div className="flex gap-3">
                            <Button disabled={form.processing}>{form.processing ? 'Menyimpan…' : 'Simpan pengguna'}</Button>
                            <Button asChild variant="outline">
                                <Link href="/users">Batal</Link>
                            </Button>
                        </div>
                    </form>
                </Panel>
            </div>
        </ManagementPage>
    );
}
