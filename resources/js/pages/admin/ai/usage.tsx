import { PageHeader } from '@/components/app-shell/page-header';
import { Badge, type BadgeProps } from '@/components/ui/badge';
import { EmptyState } from '@/components/ui/empty-state';
import { AdminLayout } from '@/layouts/admin-layout';
import type { PlatformAiUsageRow } from '@/types/admin';
import { Link } from '@inertiajs/react';
import { Gauge, SearchX } from 'lucide-react';

interface AiUsagePageProps {
    period: string;
    rows: PlatformAiUsageRow[];
}

export default function AdminAiUsage({ period, rows }: AiUsagePageProps) {
    return (
        <AdminLayout title="AI usage" breadcrumbs={[{ label: 'System' }, { label: 'AI usage' }]}>
            <div className="space-y-6">
                <PageHeader
                    title="AI usage"
                    description={`Credit consumption and generation volume by tenant for ${period}.`}
                />

                {rows.length === 0 ? (
                    <EmptyState
                        icon={Gauge}
                        title="No AI usage this period"
                        description="Balances appear here once tenants start generating content."
                    />
                ) : (
                    <div className="overflow-hidden rounded-xl border border-border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/40 text-left text-xs tracking-wide text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Tenant</th>
                                    <th className="px-4 py-3 font-medium">Status</th>
                                    <th className="px-4 py-3 font-medium text-right">Used</th>
                                    <th className="px-4 py-3 font-medium text-right">Available</th>
                                    <th className="px-4 py-3 font-medium text-right">Allowance</th>
                                    <th className="px-4 py-3 font-medium text-right">Generations</th>
                                    <th className="px-4 py-3 font-medium text-right">Failures</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {rows.map((row) => (
                                    <tr key={row.company_id} className="hover:bg-muted/30">
                                        <td className="px-4 py-3">
                                            <Link
                                                href={route('admin.tenants.show', row.uuid)}
                                                className="font-medium text-foreground hover:underline"
                                            >
                                                {row.name}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-1">
                                                <Badge variant={(row.is_active ? 'success' : 'destructive') as NonNullable<BadgeProps['variant']>}>
                                                    {row.is_active ? 'Active' : 'Suspended'}
                                                </Badge>
                                                <Badge variant={row.ai_enabled ? 'info' : 'warning'}>
                                                    {row.ai_enabled ? 'AI on' : 'AI off'}
                                                </Badge>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">{row.used}</td>
                                        <td className="px-4 py-3 text-right tabular-nums">{row.available}</td>
                                        <td className="px-4 py-3 text-right tabular-nums">{row.allowance}</td>
                                        <td className="px-4 py-3 text-right tabular-nums">{row.generations}</td>
                                        <td className="px-4 py-3 text-right tabular-nums">{row.failures}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {rows.length > 0 && rows.every((row) => row.used === 0) && (
                    <EmptyState
                        icon={SearchX}
                        title="Balances exist but nothing spent"
                        description="Tenants have credit envelopes for this period without generations yet."
                        className="border-0"
                    />
                )}
            </div>
        </AdminLayout>
    );
}
