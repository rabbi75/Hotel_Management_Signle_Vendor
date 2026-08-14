import type { BlockData, BlockRepeaterRow, BlockValue } from '@/types/cms';
import type { ComponentType } from 'react';

/**
 * A block's stored data is JSON that was validated once, on write. These
 * readers narrow it at the point of use so a renderer never has to cast, and a
 * field that was renamed or removed degrades to its fallback instead of
 * throwing mid-render.
 */
export function str(data: BlockData, key: string, fallback = ''): string {
    const value = data[key];

    return typeof value === 'string' && value !== '' ? value : fallback;
}

export function bool(data: BlockData, key: string, fallback = false): boolean {
    const value = data[key];

    return typeof value === 'boolean' ? value : fallback;
}

export function rows(data: BlockData, key: string): BlockRepeaterRow[] {
    const value = data[key];

    if (!Array.isArray(value)) {
        return [];
    }

    return value.filter((entry): entry is BlockRepeaterRow => typeof entry === 'object' && entry !== null && !Array.isArray(entry));
}

export function rowStr(row: BlockRepeaterRow, key: string, fallback = ''): string {
    const value: BlockValue | undefined = row[key];

    return typeof value === 'string' && value !== '' ? value : fallback;
}

export function rowBool(row: BlockRepeaterRow, key: string): boolean {
    return row[key] === true;
}

/** Splits a newline-separated textarea into trimmed, non-empty lines. */
export function lines(value: string): string[] {
    return value
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line !== '');
}

export interface BlockRendererProps {
    data: BlockData;
}

export type BlockRenderer = ComponentType<BlockRendererProps>;

/** Tailwind column classes, keyed by the `columns` field's allowed values. */
export const COLUMN_CLASSES: Record<string, string> = {
    '2': 'sm:grid-cols-2',
    '3': 'sm:grid-cols-2 lg:grid-cols-3',
    '4': 'sm:grid-cols-2 lg:grid-cols-4',
};
