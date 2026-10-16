import { ManagementPage } from '@/components/management';
import { InstanceTable } from '@/components/runtime';
import { Paginator } from '@/types/management';
import { RuntimeInstance } from '@/types/runtime';
export default function Requests({ instances }: { instances: Paginator<RuntimeInstance> }) {
    return (
        <ManagementPage title="Pengajuan" description="Pantau status dan riwayat pengajuan Anda.">
            <InstanceTable instances={instances} />
        </ManagementPage>
    );
}
