import { Empty, ManagementPage, Pagination, Panel } from '@/components/management';
import { Button } from '@/components/ui/button';
import { Paginator } from '@/types/management';
import { Link, useForm } from '@inertiajs/react';
interface Notification {
    id: number;
    message: string;
    workflow_instance_id: number;
    can_view_instance: boolean;
    read_at: string | null;
    created_at: string;
}
function Notice({ notification: n }: { notification: Notification }) {
    const form = useForm({});
    return (
        <article
            className={`flex flex-wrap items-center justify-between gap-4 border-b py-5 last:border-0 ${!n.read_at ? 'font-medium' : 'text-muted-foreground'}`}
        >
            <div>
                <p>{n.message}</p>
                <p className="text-muted-foreground mt-2 text-xs">
                    {new Date(n.created_at).toLocaleString('id-ID')} · {n.read_at ? 'Sudah dibaca' : 'Baru'}
                </p>
            </div>
            <div className="flex gap-2">
                {n.can_view_instance && (
                    <Button asChild variant="outline" size="sm">
                        <Link href={`/requests/${n.workflow_instance_id}`}>Lihat pengajuan</Link>
                    </Button>
                )}
                {!n.read_at && (
                    <Button size="sm" disabled={form.processing} onClick={() => form.post(`/notifications/${n.id}/read`, { preserveScroll: true })}>
                        Tandai dibaca
                    </Button>
                )}
            </div>
        </article>
    );
}
export default function Notifications({ notifications }: { notifications: Paginator<Notification> }) {
    return (
        <ManagementPage title="Notifikasi" description="Pembaruan proses dan tugas yang memerlukan perhatian Anda.">
            <Panel>
                {notifications.data.map((n) => (
                    <Notice key={n.id} notification={n} />
                ))}
                {!notifications.data.length && <Empty>Belum ada notifikasi.</Empty>}
            </Panel>
            <Pagination data={notifications} />
        </ManagementPage>
    );
}
