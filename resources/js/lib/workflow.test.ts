import { describe, expect, it } from 'vitest';
import { branches, canConnect, defaults, Definition, fieldErrors, fieldValue, FormField, nodeData } from './workflow';
const graph: Definition = {
    nodes: ['start', 'form', 'approval', 'end'].map((type, i) => ({
        id: String(i),
        type: type as 'start' | 'form' | 'approval' | 'end',
        position: { x: 0, y: 0 },
        data: { label: type },
    })),
    edges: [{ id: 'a', source: '0', target: '1', sourceHandle: 'next' }],
};
describe('graph connection safety', () => {
    it('accepts legal branches', () => {
        expect(canConnect(graph, '1', '2', 'next')).toBe(true);
        expect(canConnect(graph, '2', '3', 'approved')).toBe(true);
    });
    it('rejects self, start targets, end outputs and duplicate output', () => {
        expect(canConnect(graph, '1', '1', 'next')).toBe(false);
        expect(canConnect(graph, '1', '0', 'next')).toBe(false);
        expect(canConnect(graph, '3', '1', 'next')).toBe(false);
        expect(canConnect(graph, '0', '2', 'next')).toBe(false);
    });
    it('rejects cycles, missing nodes and invalid handles', () => {
        const chained = { ...graph, edges: [...graph.edges, { id: 'b', source: '1', target: '2', sourceHandle: 'next' as const }] };
        expect(canConnect(chained, '2', '1', 'approved')).toBe(false);
        expect(canConnect(graph, 'missing', '1', 'next')).toBe(false);
        expect(canConnect(graph, '2', '3', 'next')).toBe(false);
    });
    it('provides exact node defaults and handles', () => {
        expect(nodeData('end').outcome).toBe('completed');
        expect(nodeData('form').fields).toEqual([]);
        expect(branches('condition')).toEqual(['true', 'false']);
        expect(branches('end')).toEqual([]);
    });
});
describe('form defaults and validation', () => {
    const fields: FormField[] = [
        { key: 'amount', label: 'Jumlah', type: 'number', required: true },
        { key: 'agree', label: 'Setuju', type: 'checkbox', required: false, default_value: false },
        { key: 'file', label: 'Berkas', type: 'file', required: false },
    ];
    it('preserves scalar types and does not fabricate number values', () => {
        expect(defaults(fields)).toEqual({ amount: null, agree: false, file: null });
        expect(fieldValue(fields[0], '12.5')).toBe(12.5);
        expect(fieldValue(fields[1], 'false')).toBe(false);
        expect(fieldValue(fields[2], 'anything')).toBeNull();
    });
    it('rejects invalid numeric conversions', () => {
        expect(() => fieldValue(fields[0], '')).toThrow();
        expect(() => fieldValue(fields[0], 'abc')).toThrow();
        expect(() => fieldValue(fields[0], 'Infinity')).toThrow();
    });
    it('checks keys, labels, options and bounds', () => {
        expect(fieldErrors(fields)).toEqual([]);
        expect(
            fieldErrors([
                { ...fields[0], key: 'bad-key', min: 3, max: 1 },
                { ...fields[0], type: 'select', label: '', options: [] },
            ]),
        ).toHaveLength(4);
        expect(fieldErrors([fields[0], fields[0]])).toHaveLength(1);
    });
});
