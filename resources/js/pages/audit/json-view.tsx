import { cn } from '@/lib/utils';
import type { ReactNode } from 'react';

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function scalar(value: unknown): string {
    if (value === null || value === undefined) {
        return '—';
    }

    if (typeof value === 'boolean') {
        return value ? 'true' : 'false';
    }

    return String(value);
}

/**
 * Renders an audit record's JSON payload as nested definition lists.
 *
 * A raw `JSON.stringify` block is unreadable at a glance and, on a phone,
 * forces horizontal scrolling — the one thing these screens must not do.
 */
export function JsonView({ value, level = 0 }: { value: unknown; level?: number }): ReactNode {
    if (Array.isArray(value)) {
        if (value.length === 0) {
            return <p className="text-sm text-muted-foreground">Empty list</p>;
        }

        return (
            <ol className={cn('space-y-2', level > 0 && 'border-l border-border pl-3')}>
                {value.map((entry, index) => (
                    <li key={index} className="space-y-1">
                        <span className="text-xs text-muted-foreground">#{index + 1}</span>
                        <JsonView value={entry} level={level + 1} />
                    </li>
                ))}
            </ol>
        );
    }

    if (isRecord(value)) {
        const entries = Object.entries(value);

        if (entries.length === 0) {
            return <p className="text-sm text-muted-foreground">No details recorded</p>;
        }

        return (
            <dl className={cn('space-y-2', level > 0 && 'border-l border-border pl-3')}>
                {entries.map(([key, entry]) => (
                    <div key={key} className="min-w-0">
                        <dt className="font-mono text-xs break-all text-muted-foreground">{key}</dt>
                        <dd className="min-w-0">
                            {isRecord(entry) || Array.isArray(entry) ? (
                                <JsonView value={entry} level={level + 1} />
                            ) : (
                                <span className="text-sm break-words">{scalar(entry)}</span>
                            )}
                        </dd>
                    </div>
                ))}
            </dl>
        );
    }

    return <span className="text-sm break-words">{scalar(value)}</span>;
}

export function DetailRow({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid grid-cols-[8rem_minmax(0,1fr)] gap-3 py-2">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="min-w-0 text-sm break-words">{children}</dd>
        </div>
    );
}

/** Builds an export URL that carries the table's current query string. */
export function auditExportUrl(base: string, format: 'csv' | 'xlsx'): string {
    const url = new URL(base, window.location.origin);

    for (const [key, value] of new URLSearchParams(window.location.search)) {
        url.searchParams.append(key, value);
    }

    url.searchParams.set('format', format);

    return url.toString();
}
