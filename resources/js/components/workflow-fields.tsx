import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { FieldType, FormField, fieldValue } from '@/lib/workflow';
import { useState } from 'react';

export const fieldTypes: FieldType[] = ['text', 'textarea', 'number', 'date', 'select', 'radio', 'checkbox', 'file'];
export const control = 'bg-background w-full rounded-md border px-3 py-2 text-sm';
export function FieldEditor({ fields, onChange }: { fields: FormField[]; onChange: (fields: FormField[]) => void }) {
    const [error, setError] = useState('');
    const update = (index: number, patch: Partial<FormField>) => onChange(fields.map((f, i) => (i === index ? { ...f, ...patch } : f)));
    return (
        <div className="grid gap-4">
            <h3 className="font-semibold">Kolom formulir</h3>
            {fields.map((f, i) => (
                <fieldset key={i} className="grid gap-3 rounded-lg border p-3">
                    <legend>Kolom {i + 1}</legend>
                    <label>
                        Label
                        <Input value={f.label} onChange={(e) => update(i, { label: e.target.value })} />
                    </label>
                    <label>
                        Kunci
                        <Input value={f.key} onChange={(e) => update(i, { key: e.target.value })} />
                    </label>
                    <label>
                        Tipe
                        <select
                            className={control}
                            value={f.type}
                            onChange={(e) => {
                                const type = e.target.value as FieldType;
                                const { default_value: removed, ...rest } = f;
                                void removed;
                                onChange(fields.map((item, index) => (index === i ? { ...rest, type } : item)));
                            }}
                        >
                            {fieldTypes.map((t) => (
                                <option key={t}>{t}</option>
                            ))}
                        </select>
                    </label>
                    <label className="flex gap-2">
                        <input type="checkbox" checked={f.required} onChange={(e) => update(i, { required: e.target.checked })} />
                        Wajib diisi
                    </label>
                    <label>
                        Placeholder
                        <Input value={f.placeholder ?? ''} onChange={(e) => update(i, { placeholder: e.target.value })} />
                    </label>
                    {['select', 'radio'].includes(f.type) && (
                        <label>
                            Opsi (satu per baris)
                            <textarea
                                className={control}
                                value={(f.options ?? []).join('\n')}
                                onChange={(e) => update(i, { options: e.target.value.split('\n') })}
                            />
                        </label>
                    )}
                    {f.type !== 'file' && (
                        <label>
                            Nilai awal (kosong = tanpa default)
                            <Input
                                key={`${i}-${f.type}`}
                                defaultValue={f.default_value === undefined ? '' : String(f.default_value)}
                                onBlur={(e) => {
                                    try {
                                        setError('');
                                        if (!e.target.value) {
                                            const next = { ...f };
                                            delete next.default_value;
                                            onChange(fields.map((item, index) => (index === i ? next : item)));
                                        } else update(i, { default_value: fieldValue(f, e.target.value) });
                                    } catch (err) {
                                        setError(err instanceof Error ? err.message : 'Nilai tidak valid');
                                    }
                                }}
                            />
                            <small>Checkbox: true / false; angka tanpa pemisah ribuan.</small>
                        </label>
                    )}
                    {['number', 'text', 'textarea', 'file'].includes(f.type) && (
                        <div className="grid grid-cols-2 gap-2">
                            {(['min', 'max'] as const).map((bound) => (
                                <label key={bound}>
                                    {bound}
                                    <Input
                                        type="number"
                                        value={f[bound] ?? ''}
                                        onChange={(e) => {
                                            const next = { ...f };
                                            if (e.target.value === '') delete next[bound];
                                            else next[bound] = Number(e.target.value);
                                            onChange(fields.map((item, index) => (index === i ? next : item)));
                                        }}
                                    />
                                </label>
                            ))}
                        </div>
                    )}
                    <Button variant="outline" onClick={() => onChange(fields.filter((_, index) => index !== i))}>
                        Hapus kolom
                    </Button>
                </fieldset>
            ))}
            {error && (
                <p role="alert" className="text-destructive">
                    {error}
                </p>
            )}
            <Button
                variant="secondary"
                onClick={() =>
                    onChange([...fields, { key: `field_${crypto.randomUUID().slice(0, 8)}`, label: 'Kolom baru', type: 'text', required: false }])
                }
            >
                Tambah kolom
            </Button>
        </div>
    );
}
