import { ConfirmAction, date, Empty, ManagementPage, Pagination, Panel, Search } from '@/components/management';
import { Button } from '@/components/ui/button';
import { SharedData } from '@/types';
import { ManagedUser, Paginator } from '@/types/management';
import { Link, usePage } from '@inertiajs/react';

export default function Users({
    users,
    filters,
}: {
    users: Paginator<ManagedUser>;
    filters: { search: string };
    roles: string[];
    departments: { id: number; name: string }[];
}) {
    const { auth } = usePage<SharedData>().props;
    return (
        <ManagementPage
            title="Pengguna & peran"
            description="Kelola anggota organisasi dan hak akses mereka."
            action={
                auth.permissions.includes('users.manage') && (
                    <Button asChild>
                        <Link href="/users/create">Tambah pengguna</Link>
                    </Button>
                )
            }
        >
            <Panel>
                <Search url="/users" search={filters.search ?? ''} />
                <div className="mt-6 overflow-x-auto">
                    {users.data.length === 0 ? (
                        <Empty>Tidak ada pengguna yang sesuai dengan pencarian.</Empty>
                    ) : (
                        <table className="w-full text-left text-sm">
                            <thead className="text-muted-foreground border-b">
                                <tr>
                                    {['Pengguna', 'Departemen', 'Peran', 'Bergabung', 'Aksi'].map((h) => (
                                        <th key={h} className="px-3 py-3 font-medium">
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {users.data.map((u) => (
                                    <tr key={u.id} className="border-b last:border-0">
                                        <td className="px-3 py-5">
                                            <p className="font-semibold">
                                                {u.name}
                                                {u.id === auth.user.id && <span className="text-muted-foreground ml-2 text-xs">(Anda)</span>}
                                            </p>
                                            <p className="text-muted-foreground mt-1">{u.email}</p>
                                        </td>
                                        <td className="px-3 py-5">{u.department?.name ?? 'Tanpa departemen'}</td>
                                        <td className="px-3 py-5">
                                            <div className="flex flex-wrap gap-2">
                                                {u.roles.length ? (
                                                    u.roles.map((r) => (
                                                        <span key={r} className="bg-muted rounded-full px-2 py-1 text-xs">
                                                            {r}
                                                        </span>
                                                    ))
                                                ) : (
                                                    <span className="text-muted-foreground">Tanpa peran</span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="text-muted-foreground px-3 py-5 whitespace-nowrap">{date(u.created_at)}</td>
                                        <td className="px-3 py-5">
                                            <div className="flex gap-2">
                                                {u.can.update && (
                                                    <Button asChild variant="outline" size="sm">
                                                        <Link href={`/users/${u.id}/edit`}>Edit</Link>
                                                    </Button>
                                                )}
                                                {u.can.delete && <ConfirmAction url={`/users/${u.id}`} label="Hapus" name={u.name} />}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </Panel>
            <Pagination data={users} />
        </ManagementPage>
    );
}
