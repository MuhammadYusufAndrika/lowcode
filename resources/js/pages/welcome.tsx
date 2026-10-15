import { Button } from '@/components/ui/button';
import { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { GitBranch } from 'lucide-react';

export default function Welcome() {
    const { auth } = usePage<SharedData<false>>().props;
    return (
        <>
            <Head title="ProcessFlow" />
            <main className="bg-background flex min-h-screen items-center justify-center p-6">
                <section className="bg-card w-full max-w-2xl rounded-2xl border p-8 shadow-sm sm:p-12">
                    <GitBranch className="mb-8 size-10" />
                    <p className="text-muted-foreground text-sm font-semibold tracking-widest uppercase">ProcessFlow</p>
                    <h1 className="mt-4 text-4xl font-semibold tracking-tight">Fondasi proses yang lebih terstruktur.</h1>
                    <p className="text-muted-foreground mt-5 text-base leading-7">
                        Kelola definisi workflow, pengguna, dan departemen organisasi dalam satu ruang kerja yang terpusat.
                    </p>
                    <div className="mt-8 flex gap-3">
                        {auth.user ? (
                            <Button asChild>
                                <Link href={route('dashboard')}>Buka dasbor</Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild>
                                    <Link href={route('login')}>Masuk</Link>
                                </Button>
                                <Button asChild variant="outline">
                                    <Link href={route('register')}>Daftar</Link>
                                </Button>
                            </>
                        )}
                    </div>
                </section>
            </main>
        </>
    );
}
