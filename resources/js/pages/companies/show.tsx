import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { usePermissions } from '@/hooks/use-permissions';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Company, CompanyOwner } from '@/types/companies';
import { Link } from '@inertiajs/react';
import { format, isValid, parseISO } from 'date-fns';
import { Building2, Mail, MapPin, Settings2, Users } from 'lucide-react';

interface CompaniesShowProps {
    company: Company;
    owner: CompanyOwner | null;
    members_count: number;
}

function absolute(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = parseISO(value);

    return isValid(date) ? format(date, 'PP') : '—';
}

export default function CompaniesShow({ company, owner, members_count: membersCount }: CompaniesShowProps) {
    const { can } = usePermissions();

    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Workspaces', href: route('companies.index') },
        { label: company.name },
    ];

    const address = [
        company.address_line_1,
        company.address_line_2,
        company.city,
        company.state,
        company.postal_code,
        company.country_code,
    ].filter((part): part is string => Boolean(part));

    return (
        <AppLayout title={company.name} breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title={company.name}
                    description={company.slug}
                    actions={
                        <>
                            {can('companies.members.view') && (
                                <Button asChild variant="outline">
                                    <Link href={route('companies.members.index')}>
                                        <Users className="size-4" aria-hidden="true" />
                                        Members
                                    </Link>
                                </Button>
                            )}
                            {can('companies.update') && (
                                <Button asChild>
                                    <Link href={route('companies.edit', company.uuid)}>
                                        <Settings2 className="size-4" aria-hidden="true" />
                                        Settings
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>Identity</CardTitle>
                            <CardDescription>How this workspace presents itself.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center gap-3">
                                <Avatar size="lg" className="rounded-lg">
                                    {company.logo && <AvatarImage src={company.logo} alt="" />}
                                    <AvatarFallback className="rounded-lg">{company.initials}</AvatarFallback>
                                </Avatar>
                                <div className="min-w-0">
                                    <p className="truncate font-medium">{company.name}</p>
                                    <p className="truncate text-sm text-muted-foreground">{company.email ?? 'No contact email'}</p>
                                </div>
                            </div>

                            <Separator />

                            <dl className="grid gap-3 text-sm">
                                <div className="flex items-baseline justify-between gap-3">
                                    <dt className="text-muted-foreground">Status</dt>
                                    <dd>
                                        <Badge variant={company.is_active ? 'success' : 'secondary'}>
                                            {company.is_active ? 'Active' : 'Inactive'}
                                        </Badge>
                                    </dd>
                                </div>
                                {company.on_trial && (
                                    <div className="flex items-baseline justify-between gap-3">
                                        <dt className="text-muted-foreground">Trial ends</dt>
                                        <dd>{absolute(company.trial_ends_at)}</dd>
                                    </div>
                                )}
                                <div className="flex items-baseline justify-between gap-3">
                                    <dt className="text-muted-foreground">Members</dt>
                                    <dd className="tabular-nums">{membersCount}</dd>
                                </div>
                                <div className="flex items-baseline justify-between gap-3">
                                    <dt className="text-muted-foreground">Owner</dt>
                                    <dd className="truncate">{owner?.name ?? '—'}</dd>
                                </div>
                                <div className="flex items-baseline justify-between gap-3">
                                    <dt className="text-muted-foreground">Created</dt>
                                    <dd>{absolute(company.created_at)}</dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <MapPin className="size-4 text-muted-foreground" aria-hidden="true" />
                                Address
                            </CardTitle>
                            <CardDescription>Printed on generated documents.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {address.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No address recorded.</p>
                            ) : (
                                <address className="text-sm not-italic">
                                    {address.map((line) => (
                                        <span key={line} className="block">
                                            {line}
                                        </span>
                                    ))}
                                </address>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Building2 className="size-4 text-muted-foreground" aria-hidden="true" />
                                Business details
                            </CardTitle>
                            <CardDescription>Contact and localisation.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-3 text-sm">
                                <div className="flex items-baseline justify-between gap-3">
                                    <dt className="text-muted-foreground">Phone</dt>
                                    <dd className="truncate">{company.phone ?? '—'}</dd>
                                </div>
                                <div className="flex items-baseline justify-between gap-3">
                                    <dt className="text-muted-foreground">Website</dt>
                                    <dd className="min-w-0 truncate">
                                        {company.website ? (
                                            <a
                                                href={company.website}
                                                className="underline underline-offset-4"
                                                rel="noreferrer noopener"
                                                target="_blank"
                                            >
                                                {company.website}
                                            </a>
                                        ) : (
                                            '—'
                                        )}
                                    </dd>
                                </div>
                                <div className="flex items-baseline justify-between gap-3">
                                    <dt className="text-muted-foreground">Tax ID</dt>
                                    <dd className="truncate">{company.tax_id ?? '—'}</dd>
                                </div>
                                <div className="flex items-baseline justify-between gap-3">
                                    <dt className="text-muted-foreground">Timezone</dt>
                                    <dd className="truncate">{company.timezone}</dd>
                                </div>
                                <div className="flex items-baseline justify-between gap-3">
                                    <dt className="text-muted-foreground">Currency</dt>
                                    <dd>{company.currency}</dd>
                                </div>
                                <div className="flex items-baseline justify-between gap-3">
                                    <dt className="text-muted-foreground">Language</dt>
                                    <dd className="uppercase">{company.locale}</dd>
                                </div>
                            </dl>

                            {owner && (
                                <p className="mt-4 flex items-center gap-1.5 text-sm text-muted-foreground">
                                    <Mail className="size-3.5" aria-hidden="true" />
                                    <span className="truncate">{owner.email}</span>
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
