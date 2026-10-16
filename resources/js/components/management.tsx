import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { SharedData } from '@/types';
import { Paginator } from '@/types/management';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, ReactNode, useState } from 'react';

export function ManagementPage({
    title,
    description,
    children,
    action,
}: {
    title: string;
    description: string;
    children: ReactNode;
    action?: ReactNode;
}) {
    const { flash, errors } = usePage<SharedData>().props;
    return (
        <AppLayout breadcrumbs={[{ title, href: usePage().url.split('?')[0] }]}>
            <Head title={title} />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-8">
                <header className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p className="text-muted-foreground mb-2 text-xs font-semibold tracking-widest uppercase">ProcessFlow / Ruang kerja</p>
                        <h1 className="text-3xl font-semibold tracking-tight">{title}</h1>
                        <p className="text-muted-foreground mt-2 text-sm">{description}</p>
                    </div>
                    {action}
                </header>
                {flash?.success && (
                    <div role="status" className="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm">
                        {flash.success}
                    </div>
                )}
                {Object.keys(errors).length > 0 && (
                    <div role="alert" className="border-destructive/30 bg-destructive/5 text-destructive rounded-xl border p-4 text-sm">
                        <p className="font-semibold">Periksa kembali data Anda.</p>
                        {Object.entries(errors).map(([key, value]) => (
                            <p key={key}>{value}</p>
                        ))}
                    </div>
                )}
                {children}
            </div>
        </AppLayout>
    );
}
export function Panel({ children }: { children: ReactNode }) {
    return <div className="bg-card rounded-xl border p-5 shadow-sm md:p-6">{children}</div>;
}
export function Field({ label, name, error, children }: { label: string; name: string; error?: string; children: ReactNode }) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
export function Search({ url, search, status }: { url: string; search: string; status?: string }) {
    const [value, setValue] = useState(search);
    const [selected, setSelected] = useState(status ?? '');
    function submit(event: FormEvent) {
        event.preventDefault();
        router.get(url, { search: value, ...(status !== undefined ? { status: selected } : {}) }, { preserveState: true, replace: true });
    }
    return (
        <form onSubmit={submit} className="flex flex-wrap gap-3">
            <Input
                aria-label="Cari data"
                placeholder="Cari nama atau kode…"
                className="w-full sm:max-w-sm"
                value={value}
                onChange={(event) => setValue(event.target.value)}
            />
            {status !== undefined && (
                <select
                    aria-label="Filter status"
                    className="bg-background rounded-md border px-3 text-sm"
                    value={selected}
                    onChange={(event) => setSelected(event.target.value)}
                >
                    <option value="">Semua status</option>
                    <option value="draft">Draf</option>
                    <option value="archived">Diarsipkan</option>
                </select>
            )}
            <Button type="submit" variant="secondary">
                Cari
            </Button>
            {(search || status) && (
                <Button asChild variant="ghost">
                    <Link href={url}>Reset</Link>
                </Button>
            )}
        </form>
    );
}
export function Pagination<T>({ data }: { data: Paginator<T> }) {
    return (
        <nav aria-label="Paginasi" className="flex flex-wrap items-center justify-between gap-4 text-sm">
            <p className="text-muted-foreground">
                {data.from ?? 0}–{data.to ?? 0} dari {data.total} data · Halaman {data.current_page} / {data.last_page}
            </p>
            <div className="flex gap-2">
                {data.prev_page_url ? (
                    <Button asChild variant="outline">
                        <Link href={data.prev_page_url} preserveScroll>
                            Sebelumnya
                        </Link>
                    </Button>
                ) : (
                    <Button disabled variant="outline">
                        Sebelumnya
                    </Button>
                )}
                {data.next_page_url ? (
                    <Button asChild variant="outline">
                        <Link href={data.next_page_url} preserveScroll>
                            Berikutnya
                        </Link>
                    </Button>
                ) : (
                    <Button disabled variant="outline">
                        Berikutnya
                    </Button>
                )}
            </div>
        </nav>
    );
}
export function Empty({ children }: { children: ReactNode }) {
    return <div className="text-muted-foreground py-12 text-center text-sm">{children}</div>;
}
export function ConfirmAction({ url, label, name, method = 'delete' }: { url: string; label: string; name: string; method?: 'delete' | 'post' }) {
    const [open, setOpen] = useState(false);
    const form = useForm({});
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    {label}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    {label} {name}?
                </DialogTitle>
                <DialogDescription>
                    {method === 'delete'
                        ? 'Data yang dihapus tidak dapat dipulihkan. Pastikan data ini tidak lagi diperlukan.'
                        : 'Workflow akan diarsipkan. Pastikan perubahan ini sesuai kebutuhan Anda.'}
                </DialogDescription>
                {Object.values(form.errors).map((error, index) => (
                    <InputError key={index} message={String(error)} />
                ))}
                <DialogFooter>
                    <Button variant="outline" onClick={() => setOpen(false)} disabled={form.processing}>
                        Batal
                    </Button>
                    <Button
                        variant="destructive"
                        disabled={form.processing}
                        onClick={() => form[method](url, { preserveScroll: true, onSuccess: () => setOpen(false) })}
                    >
                        {form.processing ? 'Memproses…' : label}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
export function date(value: string) {
    return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}
