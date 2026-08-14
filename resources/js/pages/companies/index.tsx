import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Progress } from '@/components/ui/progress';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem, CompanySummary } from '@/types';
import { Link, router } from '@inertiajs/react';
import { Building2, Check, Plus, Settings2 } from 'lucide-react';

interface CompaniesIndexProps {
    companies: CompanySummary[];
    current: string | null;
    can: { create: boolean };
    max_owned: number;
    owned_count: number;
}

export default function CompaniesIndex({ companies, current, can, max_owned: maxOwned, owned_count: ownedCount }: CompaniesIndexProps) {
    const { can: allows } = usePermissions();
    const atLimit = maxOwned > 0 && ownedCount >= maxOwned;

    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Dashboard', href: routeUrl('dashboard') ?? undefined }, { label: 'Workspaces' }];

    return (
        <AppLayout title="Workspaces" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Workspaces"
                    description="Every workspace you belong to. Switching changes what the rest of the application shows."
                    actions={
                        can.create && !atLimit ? (
                            <Button asChild>
                                <Link href={route('companies.create')}>
                                    <Plus className="size-4" aria-hidden="true" />
                                    New workspace
                                </Link>
                            </Button>
                        ) : null
                    }
                />

                {maxOwned > 0 && (
                    <Card>
                        <CardContent className="flex flex-wrap items-center gap-4 py-4">
                            <div className="min-w-48 flex-1 space-y-1.5">
                                <p className="text-sm font-medium">
                                    You own {ownedCount} of {maxOwned} workspaces
                                </p>
                                <Progress value={Math.min(100, (ownedCount / maxOwned) * 100)} aria-label="Workspaces owned" />
                            </div>
                            {atLimit && <Badge variant="warning">Limit reached</Badge>}
                        </CardContent>
                    </Card>
                )}

                {companies.length === 0 ? (
                    <EmptyState
                        icon={Building2}
                        title="You are not in any workspace"
                        description="Create one, or ask a colleague to invite you to theirs."
                        action={
                            can.create ? (
                                <Button asChild size="sm">
                                    <Link href={route('companies.create')}>
                                        <Plus className="size-4" aria-hidden="true" />
                                        New workspace
                                    </Link>
                                </Button>
                            ) : null
                        }
                    />
                ) : (
                    <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {companies.map((company) => {
                            const active = company.uuid === current;

                            return (
                                <li key={company.uuid}>
                                    <Card className={cn('h-full transition-colors', active && 'border-primary')}>
                                        <CardContent className="flex h-full flex-col gap-4 py-5">
                                            <div className="flex items-start gap-3">
                                                <Avatar size="lg" className="rounded-lg">
                                                    {company.logo && <AvatarImage src={company.logo} alt="" />}
                                                    <AvatarFallback className="rounded-lg">{company.initials}</AvatarFallback>
                                                </Avatar>
                                                <div className="min-w-0 flex-1">
                                                    <h2 className="truncate font-medium">
                                                        <Link
                                                            href={route('companies.show', company.uuid)}
                                                            className="rounded-sm outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring"
                                                        >
                                                            {company.name}
                                                        </Link>
                                                    </h2>
                                                    <p className="truncate text-xs text-muted-foreground">{company.slug}</p>
                                                </div>
                                                <Badge variant={active ? 'default' : 'outline'}>{company.role}</Badge>
                                            </div>

                                            <div className="mt-auto flex flex-wrap items-center gap-2">
                                                {active ? (
                                                    <span className="inline-flex items-center gap-1.5 text-sm text-success">
                                                        <Check className="size-4" aria-hidden="true" />
                                                        Active workspace
                                                    </span>
                                                ) : (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => router.post(route('companies.switch', company.uuid))}
                                                    >
                                                        Switch to this workspace
                                                    </Button>
                                                )}

                                                {allows('companies.update') && (
                                                    <Button asChild size="sm" variant="ghost">
                                                        <Link href={route('companies.edit', company.uuid)}>
                                                            <Settings2 className="size-4" aria-hidden="true" />
                                                            Settings
                                                        </Link>
                                                    </Button>
                                                )}
                                            </div>
                                        </CardContent>
                                    </Card>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </AppLayout>
    );
}
