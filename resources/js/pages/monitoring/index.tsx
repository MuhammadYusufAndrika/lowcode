import { ManagementPage } from '@/components/management';
import { InstanceTable, Metrics, StatusFilter } from '@/components/runtime';
import { Paginator } from '@/types/management';
import { RuntimeInstance } from '@/types/runtime';
export default function Monitoring({
    instances,
    stats,
    filters,
}: {
    instances: Paginator<RuntimeInstance>;
    stats: Record<string, number>;
    filters: { status: string };
}) {
    return (
        <ManagementPage title="Monitoring proses" description="Pantau seluruh pengajuan dan identifikasi proses yang membutuhkan perhatian.">
            <Metrics stats={stats} />
            <StatusFilter url="/monitoring" value={filters.status} statuses={['', 'running', 'completed', 'rejected', 'failed', 'cancelled']} />
            <InstanceTable instances={instances} />
        </ManagementPage>
    );
}
