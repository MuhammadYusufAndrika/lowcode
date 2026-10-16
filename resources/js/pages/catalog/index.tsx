import { Empty, ManagementPage, Panel } from '@/components/management';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { CatalogWorkflow } from '@/types/runtime';
import { Link } from '@inertiajs/react';
import { useState } from 'react';
export default function Catalog({ workflows }: { workflows: CatalogWorkflow[] }) {
    const [search, setSearch] = useState('');
    const visible = workflows.filter((w) => `${w.name} ${w.code} ${w.category}`.toLowerCase().includes(search.toLowerCase()));
    return (
        <ManagementPage title="Katalog layanan" description="Pilih proses yang sudah diterbitkan untuk memulai pengajuan.">
            <Input
                aria-label="Cari layanan"
                placeholder="Cari layanan atau kategori…"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="max-w-md"
            />
            <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                {visible.map((w) => (
                    <Panel key={w.id}>
                        <div className="flex h-full flex-col gap-4">
                            <p className="text-xs font-semibold tracking-wider text-indigo-500 uppercase">
                                {w.category || 'Layanan organisasi'} · v{w.version}
                            </p>
                            <h2 className="text-xl font-semibold">{w.name}</h2>
                            <p className="text-muted-foreground flex-1 text-sm">{w.description || w.code}</p>
                            <Button asChild>
                                <Link href={`/catalog/${w.id}`}>Ajukan layanan</Link>
                            </Button>
                        </div>
                    </Panel>
                ))}
            </div>
            {!visible.length && <Empty>Belum ada layanan terbit yang sesuai.</Empty>}
        </ManagementPage>
    );
}
