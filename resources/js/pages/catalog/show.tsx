import { Field, ManagementPage, Panel } from '@/components/management';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { control } from '@/components/workflow-fields';
import { defaults, FormField, Scalar } from '@/lib/workflow';
import { CatalogWorkflow } from '@/types/runtime';
import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
export default function CatalogForm({
    workflow,
    version,
    fields,
}: {
    workflow: CatalogWorkflow;
    version: { id: number; version: number };
    fields: FormField[];
}) {
    const [key] = useState(() => crypto.randomUUID());
    const form = useForm<{ workflow_id: number; version_id: number; idempotency_key: string; data: Record<string, Scalar | File> }>({
        workflow_id: workflow.id,
        version_id: version.id,
        idempotency_key: key,
        data: defaults(fields),
    });
    const set = (name: string, value: Scalar | File) => form.setData('data', { ...form.data.data, [name]: value });
    return (
        <ManagementPage title={workflow.name} description={`${workflow.description ?? 'Lengkapi formulir pengajuan.'} · Versi ${version.version}`}>
            <div className="max-w-3xl">
                <Panel>
                    <form
                        className="grid gap-6"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post('/requests', { forceFormData: Object.values(form.data.data).some((value) => value instanceof File) });
                        }}
                    >
                        {fields.map((f) => (
                            <Field
                                key={f.key}
                                name={f.key}
                                label={`${f.label}${f.required ? ' *' : ''}`}
                                error={form.errors[`data.${f.key}` as keyof typeof form.errors]}
                            >
                                {f.type === 'textarea' ? (
                                    <textarea
                                        id={f.key}
                                        required={f.required}
                                        className={control}
                                        placeholder={f.placeholder ?? ''}
                                        value={String(form.data.data[f.key] ?? '')}
                                        minLength={f.min}
                                        maxLength={f.max}
                                        onChange={(e) => set(f.key, e.target.value)}
                                    />
                                ) : f.type === 'select' ? (
                                    <select
                                        id={f.key}
                                        required={f.required}
                                        className={control}
                                        value={String(form.data.data[f.key] ?? '')}
                                        onChange={(e) => set(f.key, e.target.value)}
                                    >
                                        <option value="">Pilih opsi</option>
                                        {f.options?.map((o) => <option key={o}>{o}</option>)}
                                    </select>
                                ) : f.type === 'radio' ? (
                                    <fieldset id={f.key} className="grid gap-3">
                                        <legend className="sr-only">{f.label}</legend>
                                        {f.options?.map((o) => (
                                            <label className="flex items-center gap-3" key={o}>
                                                <input
                                                    type="radio"
                                                    name={f.key}
                                                    required={f.required}
                                                    value={o}
                                                    checked={form.data.data[f.key] === o}
                                                    onChange={() => set(f.key, o)}
                                                />
                                                {o}
                                            </label>
                                        ))}
                                    </fieldset>
                                ) : f.type === 'checkbox' ? (
                                    <label className="flex items-center gap-3">
                                        <input
                                            id={f.key}
                                            type="checkbox"
                                            checked={form.data.data[f.key] === true}
                                            onChange={(e) => set(f.key, e.target.checked)}
                                        />
                                        Ya
                                    </label>
                                ) : f.type === 'file' ? (
                                    <Input id={f.key} type="file" required={f.required} onChange={(e) => set(f.key, e.target.files?.[0] ?? null)} />
                                ) : (
                                    <Input
                                        id={f.key}
                                        type={f.type}
                                        step={f.type === 'number' ? 'any' : undefined}
                                        min={f.type === 'number' ? f.min : undefined}
                                        max={f.type === 'number' ? f.max : undefined}
                                        minLength={f.type === 'text' ? f.min : undefined}
                                        maxLength={f.type === 'text' ? f.max : undefined}
                                        required={f.required}
                                        placeholder={f.placeholder ?? ''}
                                        value={String(form.data.data[f.key] ?? '')}
                                        onChange={(e) =>
                                            set(f.key, f.type === 'number' ? (e.target.value === '' ? null : Number(e.target.value)) : e.target.value)
                                        }
                                    />
                                )}
                            </Field>
                        ))}
                        {!fields.length && <p className="text-muted-foreground text-sm">Layanan ini tidak memerlukan data tambahan.</p>}
                        {Object.entries(form.errors)
                            .filter(([name]) => !name.startsWith('data.'))
                            .map(([name, message]) => (
                                <p key={name} role="alert" className="text-destructive text-sm">
                                    {message}
                                </p>
                            ))}
                        {form.progress && <progress className="w-full" value={form.progress.percentage} max="100" aria-label="Progres unggahan" />}
                        <div className="flex gap-3">
                            <Button disabled={form.processing}>{form.processing ? 'Mengirim…' : 'Kirim pengajuan'}</Button>
                            <Button asChild variant="outline">
                                <Link href="/catalog">Kembali</Link>
                            </Button>
                        </div>
                        <p className="text-muted-foreground text-xs">
                            Pengajuan menggunakan versi terbit ini. Pengiriman ulang tidak membuat pengajuan ganda.
                        </p>
                    </form>
                </Panel>
            </div>
        </ManagementPage>
    );
}
