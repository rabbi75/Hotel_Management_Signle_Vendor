import { PageHeader } from '@/components/app-shell/page-header';
import { useConfirm } from '@/components/feedback/use-confirm';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge, type BadgeProps } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { AdminLayout } from '@/layouts/admin-layout';
import { cn } from '@/lib/utils';
import type {
    TenantAiSummary,
    TenantHotelFootprint,
    TenantMember,
    TenantRow,
    TenantSubscriptionRow,
    TenantSupportNote,
    UsageMeter,
} from '@/types/admin';
import { Link, router } from '@inertiajs/react';
import { format, isValid, parseISO } from 'date-fns';
import {
    ArrowLeft,
    BedDouble,
    Building2,
    CreditCard,
    Globe2,
    LifeBuoy,
    LogIn,
    Mail,
    MapPin,
    Phone,
    Pin,
    ShieldOff,
    ShieldCheck,
    Sparkles,
    Trash2,
    Users,
    Wrench,
} from 'lucide-react';
import { useState } from 'react';

interface TenantShowProps {
    tenant: TenantRow;
    members: TenantMember[];
    meters: UsageMeter[];
    subscriptions: TenantSubscriptionRow[];
    plans: Record<string, string>;
    counts: { members: number; departments: number; teams: number; open_tickets: number };
    hotel: TenantHotelFootprint;
    ai: TenantAiSummary;
    support_notes: TenantSupportNote[];
    can: { manage: boolean; impersonate: boolean };
}

const STATUS_VARIANT: Record<string, NonNullable<BadgeProps['variant']>> = {
    active: 'success',
    trialing: 'info',
    past_due: 'warning',
    canceled: 'destructive',
    expired: 'destructive',
    incomplete: 'secondary',
};

const ROLE_LABELS: Record<string, string> = {
    owner: 'Owner',
    admin: 'Admin',
    member: 'Member',
    guest: 'Guest',
};

function meterLabel(meter: UsageMeter): string {
    return meter.limit < 0 ? `${meter.used} / ∞` : `${meter.used} / ${meter.limit}`;
}

function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const date = parseISO(value);

    return isValid(date) ? format(date, 'd MMM yyyy') : '—';
}

function formatDateTime(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const date = parseISO(value);

    return isValid(date) ? format(date, 'd MMM yyyy HH:mm') : '—';
}

function DetailItem({ icon: Icon, label, value }: { icon: typeof Mail; label: string; value: string | null | undefined }) {
    return (
        <div className="flex items-start gap-3">
            <div className="mt-0.5 rounded-md bg-muted p-2 text-muted-foreground">
                <Icon className="size-4" aria-hidden="true" />
            </div>
            <div className="min-w-0">
                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">{label}</p>
                <p className={cn('truncate text-sm font-medium', !value && 'text-muted-foreground')}>{value || '—'}</p>
            </div>
        </div>
    );
}

function MetricCard({
    icon: Icon,
    label,
    value,
}: {
    icon: typeof Users;
    label: string;
    value: string | number;
}) {
    return (
        <Card>
            <CardContent className="flex items-center gap-3 pt-6">
                <div className="rounded-md bg-muted p-2 text-muted-foreground">
                    <Icon className="size-4" aria-hidden="true" />
                </div>
                <div>
                    <p className="text-xs text-muted-foreground">{label}</p>
                    <p className="text-2xl font-semibold tracking-tight">{value}</p>
                </div>
            </CardContent>
        </Card>
    );
}

export default function TenantShow({
    tenant,
    members,
    meters,
    subscriptions,
    plans,
    counts,
    hotel,
    ai,
    support_notes,
    can,
}: TenantShowProps) {
    const confirm = useConfirm();
    const [plan, setPlan] = useState(tenant.plan_slug ?? '');
    const [interval, setInterval] = useState(tenant.subscription_interval ?? 'monthly');
    const [savingPlan, setSavingPlan] = useState(false);
    const [creditDelta, setCreditDelta] = useState('1000');
    const [creditReason, setCreditReason] = useState('');
    const [noteBody, setNoteBody] = useState('');
    const [notePinned, setNotePinned] = useState(false);

    function assignPlan(): void {
        if (!plan) {
            return;
        }

        setSavingPlan(true);
        router.put(
            route('admin.tenants.subscription.update', tenant.uuid),
            { plan, interval },
            {
                preserveScroll: true,
                onFinish: () => setSavingPlan(false),
            },
        );
    }

    function extendTrial(): void {
        router.post(route('admin.tenants.subscription.trial', tenant.uuid), { days: 14 }, { preserveScroll: true });
    }

    async function toggleStatus(): Promise<void> {
        const suspending = tenant.is_active;

        const ok = await confirm({
            title: suspending ? 'Suspend tenant?' : 'Restore tenant?',
            description: suspending
                ? 'Members will be locked out until this tenant is restored.'
                : 'Members will regain access immediately.',
            confirmLabel: suspending ? 'Suspend' : 'Restore',
            variant: suspending ? 'destructive' : 'default',
        });

        if (!ok) {
            return;
        }

        router.patch(route('admin.tenants.status', tenant.uuid), { is_active: !tenant.is_active }, { preserveScroll: true });
    }

    function impersonate(): void {
        router.post(route('admin.tenants.impersonate', tenant.uuid));
    }

    function adjustCredits(): void {
        const credits = Number.parseInt(creditDelta, 10);

        if (!Number.isFinite(credits) || credits === 0) {
            return;
        }

        router.post(
            route('admin.tenants.ai.credits', tenant.uuid),
            { credits, reason: creditReason || undefined },
            { preserveScroll: true, onSuccess: () => setCreditReason('') },
        );
    }

    function toggleAi(enabled: boolean): void {
        router.patch(route('admin.tenants.ai.toggle', tenant.uuid), { enabled }, { preserveScroll: true });
    }

    function saveNote(): void {
        if (!noteBody.trim()) {
            return;
        }

        router.post(
            route('admin.tenants.notes.store', tenant.uuid),
            { body: noteBody, is_pinned: notePinned },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setNoteBody('');
                    setNotePinned(false);
                },
            },
        );
    }

    async function deleteNote(noteId: number): Promise<void> {
        const ok = await confirm({
            title: 'Delete support note?',
            description: 'This removes the note from the tenant dossier.',
            confirmLabel: 'Delete',
            variant: 'destructive',
        });

        if (!ok) {
            return;
        }

        router.delete(route('admin.tenants.notes.destroy', [tenant.uuid, noteId]), { preserveScroll: true });
    }

    const location = [tenant.city, tenant.country_code].filter(Boolean).join(', ');

    return (
        <AdminLayout
            title={tenant.name}
            breadcrumbs={[
                { label: 'Tenants', href: route('admin.tenants.index') },
                { label: tenant.name },
            ]}
        >
            <div className="mx-auto w-full max-w-6xl space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex min-w-0 items-start gap-4">
                        <Avatar className="size-16 rounded-xl">
                            {tenant.logo && <AvatarImage src={tenant.logo} alt="" />}
                            <AvatarFallback className="rounded-xl text-lg font-semibold">{tenant.initials}</AvatarFallback>
                        </Avatar>
                        <div className="min-w-0 space-y-2">
                            <PageHeader
                                title={tenant.name}
                                description={tenant.owner ? `Owned by ${tenant.owner.name} · ${tenant.owner.email}` : 'No owner assigned'}
                                className="p-0"
                            />
                            <div className="flex flex-wrap items-center gap-2">
                                {tenant.is_active ? (
                                    <Badge variant="success">Active</Badge>
                                ) : (
                                    <Badge variant="destructive">Suspended</Badge>
                                )}
                                {tenant.plan ? <Badge variant="secondary">{tenant.plan}</Badge> : <Badge variant="outline">No plan</Badge>}
                                {tenant.subscription_status && (
                                    <Badge variant={STATUS_VARIANT[tenant.subscription_status] ?? 'secondary'}>
                                        {tenant.subscription_status.replace('_', ' ')}
                                    </Badge>
                                )}
                                {ai.enabled ? <Badge variant="info">AI on</Badge> : <Badge variant="warning">AI off</Badge>}
                                <span className="text-xs text-muted-foreground">Created {formatDate(tenant.created_at)}</span>
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="outline" asChild>
                            <Link href={route('admin.tenants.index')}>
                                <ArrowLeft className="size-4" aria-hidden="true" />
                                Back
                            </Link>
                        </Button>
                        {can.impersonate && tenant.is_active && tenant.owner && (
                            <Button variant="outline" onClick={impersonate}>
                                <LogIn className="size-4" aria-hidden="true" />
                                Login as client
                            </Button>
                        )}
                        {can.manage && (
                            <Button variant={tenant.is_active ? 'destructive' : 'default'} onClick={() => void toggleStatus()}>
                                {tenant.is_active ? (
                                    <>
                                        <ShieldOff className="size-4" aria-hidden="true" />
                                        Suspend
                                    </>
                                ) : (
                                    <>
                                        <ShieldCheck className="size-4" aria-hidden="true" />
                                        Restore
                                    </>
                                )}
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <MetricCard icon={Users} label="Members" value={counts.members} />
                    <MetricCard icon={Building2} label="Hotels" value={hotel.hotels} />
                    <MetricCard icon={BedDouble} label="Rooms" value={hotel.rooms} />
                    <MetricCard icon={CreditCard} label="Renews" value={formatDate(tenant.renews_at)} />
                    <Card>
                        <CardContent className="flex items-center justify-between gap-3 pt-6">
                            <div className="flex items-center gap-3">
                                <div className="rounded-md bg-muted p-2 text-muted-foreground">
                                    <LifeBuoy className="size-4" aria-hidden="true" />
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">Open tickets</p>
                                    <p className="text-2xl font-semibold tracking-tight">{counts.open_tickets}</p>
                                </div>
                            </div>
                            <Button asChild variant="outline" size="sm">
                                <Link href={`${route('admin.tickets.index')}?company=${tenant.uuid}`}>View</Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                <Tabs defaultValue="overview" className="space-y-4">
                    <TabsList>
                        <TabsTrigger value="overview">Overview</TabsTrigger>
                        <TabsTrigger value="hotel">Hotel</TabsTrigger>
                        <TabsTrigger value="ai">AI</TabsTrigger>
                        <TabsTrigger value="notes">Notes ({support_notes.length})</TabsTrigger>
                        <TabsTrigger value="members">Members ({counts.members})</TabsTrigger>
                        <TabsTrigger value="subscription">Subscription</TabsTrigger>
                        <TabsTrigger value="usage">Usage</TabsTrigger>
                    </TabsList>

                    <TabsContent value="overview" className="space-y-4">
                        <div className="grid gap-4 lg:grid-cols-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Tenant profile</CardTitle>
                                    <CardDescription>Contact and locale details for this workspace.</CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <DetailItem icon={Mail} label="Email" value={tenant.email} />
                                    <DetailItem icon={Phone} label="Phone" value={tenant.phone} />
                                    <DetailItem icon={Globe2} label="Website" value={tenant.website} />
                                    <DetailItem icon={MapPin} label="Location" value={location} />
                                    <DetailItem icon={Globe2} label="Timezone" value={tenant.timezone} />
                                    <DetailItem icon={CreditCard} label="Currency" value={tenant.currency} />
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Owner</CardTitle>
                                    <CardDescription>Primary account for this tenant.</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    {tenant.owner ? (
                                        <div className="flex items-center gap-3">
                                            <Avatar className="size-11">
                                                <AvatarFallback>{tenant.owner.name.slice(0, 2).toUpperCase()}</AvatarFallback>
                                            </Avatar>
                                            <div className="min-w-0">
                                                <p className="truncate font-medium">{tenant.owner.name}</p>
                                                <p className="truncate text-sm text-muted-foreground">{tenant.owner.email}</p>
                                            </div>
                                        </div>
                                    ) : (
                                        <p className="text-sm text-muted-foreground">No owner on file.</p>
                                    )}
                                    <Separator className="my-4" />
                                    <div className="grid gap-2 text-sm">
                                        <div className="flex justify-between gap-3">
                                            <span className="text-muted-foreground">Slug</span>
                                            <span className="font-medium">{tenant.slug}</span>
                                        </div>
                                        <div className="flex justify-between gap-3">
                                            <span className="text-muted-foreground">Trial ends</span>
                                            <span className="font-medium">{formatDate(tenant.trial_ends_at)}</span>
                                        </div>
                                        <div className="flex justify-between gap-3">
                                            <span className="text-muted-foreground">Locale</span>
                                            <span className="font-medium">{tenant.locale ?? '—'}</span>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </TabsContent>

                    <TabsContent value="hotel" className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <MetricCard icon={Building2} label="Hotels" value={hotel.hotels} />
                            <MetricCard icon={BedDouble} label="Active rooms" value={hotel.rooms} />
                            <MetricCard icon={Users} label="Workspaces" value={hotel.workspaces} />
                            <MetricCard icon={Sparkles} label="Occupancy" value={`${hotel.occupancy_rate}%`} />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <MetricCard icon={Users} label="In-house" value={hotel.in_house_guests} />
                            <MetricCard icon={LogIn} label="Arrivals today" value={hotel.arrivals_today} />
                            <MetricCard icon={BedDouble} label="Open housekeeping" value={hotel.pending_housekeeping} />
                            <MetricCard icon={Wrench} label="Open maintenance" value={hotel.open_maintenance} />
                        </div>
                    </TabsContent>

                    <TabsContent value="ai" className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>AI policy</CardTitle>
                                <CardDescription>Period {ai.period}. Disable blocks generations for this tenant only.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <div className="flex items-center justify-between gap-4 rounded-lg border border-border p-4">
                                    <div>
                                        <p className="font-medium">AI enabled</p>
                                        <p className="text-sm text-muted-foreground">
                                            {ai.enabled ? 'Members can run assistants and hotel assists.' : 'All AI generation is blocked.'}
                                        </p>
                                    </div>
                                    <Switch
                                        checked={ai.enabled}
                                        disabled={!can.manage}
                                        onCheckedChange={(checked) => toggleAi(checked)}
                                    />
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                    <div>
                                        <p className="text-xs text-muted-foreground">Available</p>
                                        <p className="text-2xl font-semibold">{ai.available}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">Used</p>
                                        <p className="text-2xl font-semibold">{ai.used}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">Allowance</p>
                                        <p className="text-2xl font-semibold">{ai.allowance}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">Generations / failures</p>
                                        <p className="text-2xl font-semibold">
                                            {ai.generations_this_period} / {ai.failed_this_period}
                                        </p>
                                    </div>
                                </div>

                                {can.manage && (
                                    <div className="flex flex-wrap items-end gap-3 border-t border-border pt-4">
                                        <div className="space-y-1.5">
                                            <Label htmlFor="credit-delta">Adjust credits</Label>
                                            <Input
                                                id="credit-delta"
                                                type="number"
                                                className="w-36"
                                                value={creditDelta}
                                                onChange={(event) => setCreditDelta(event.target.value)}
                                            />
                                        </div>
                                        <div className="min-w-56 flex-1 space-y-1.5">
                                            <Label htmlFor="credit-reason">Reason</Label>
                                            <Input
                                                id="credit-reason"
                                                value={creditReason}
                                                onChange={(event) => setCreditReason(event.target.value)}
                                                placeholder="Optional note for the audit log"
                                            />
                                        </div>
                                        <Button onClick={adjustCredits}>Apply adjustment</Button>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="notes" className="space-y-4">
                        {can.manage && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Add support note</CardTitle>
                                    <CardDescription>Internal only — not visible to the tenant.</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <Textarea
                                        value={noteBody}
                                        onChange={(event) => setNoteBody(event.target.value)}
                                        placeholder="Context for the next operator who opens this account…"
                                        rows={4}
                                    />
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <label className="flex items-center gap-2 text-sm">
                                            <Switch checked={notePinned} onCheckedChange={setNotePinned} />
                                            Pin to top
                                        </label>
                                        <Button onClick={saveNote} disabled={!noteBody.trim()}>
                                            Save note
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle>Timeline</CardTitle>
                                <CardDescription>Recent operator notes on this tenant.</CardDescription>
                            </CardHeader>
                            <CardContent className="p-0">
                                {support_notes.length === 0 ? (
                                    <p className="px-6 pb-6 text-sm text-muted-foreground">No support notes yet.</p>
                                ) : (
                                    <div className="divide-y divide-border">
                                        {support_notes.map((note) => (
                                            <div key={note.id} className="flex items-start justify-between gap-3 px-6 py-4">
                                                <div className="min-w-0 space-y-1">
                                                    <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                                        {note.is_pinned && (
                                                            <Badge variant="secondary" className="gap-1">
                                                                <Pin className="size-3" aria-hidden="true" />
                                                                Pinned
                                                            </Badge>
                                                        )}
                                                        <span>{note.admin ?? 'Operator'}</span>
                                                        <span>·</span>
                                                        <span>{formatDateTime(note.created_at)}</span>
                                                    </div>
                                                    <p className="whitespace-pre-wrap text-sm">{note.body}</p>
                                                </div>
                                                {can.manage && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="shrink-0 text-muted-foreground"
                                                        onClick={() => void deleteNote(note.id)}
                                                    >
                                                        <Trash2 className="size-4" aria-hidden="true" />
                                                        <span className="sr-only">Delete note</span>
                                                    </Button>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="members">
                        <Card>
                            <CardHeader>
                                <CardTitle>Members</CardTitle>
                                <CardDescription>People with access to this tenant.</CardDescription>
                            </CardHeader>
                            <CardContent className="p-0">
                                {members.length === 0 ? (
                                    <p className="px-6 pb-6 text-sm text-muted-foreground">No members yet.</p>
                                ) : (
                                    <div className="divide-y divide-border">
                                        {members.map((member) => (
                                            <div key={member.id} className="flex items-center justify-between gap-3 px-6 py-3">
                                                <div className="flex min-w-0 items-center gap-3">
                                                    <Avatar className="size-9">
                                                        {member.avatar && <AvatarImage src={member.avatar} alt="" />}
                                                        <AvatarFallback>{member.initials ?? member.name.slice(0, 2).toUpperCase()}</AvatarFallback>
                                                    </Avatar>
                                                    <div className="min-w-0">
                                                        <p className="truncate text-sm font-medium">{member.name}</p>
                                                        <p className="truncate text-xs text-muted-foreground">{member.email}</p>
                                                    </div>
                                                </div>
                                                <div className="flex shrink-0 items-center gap-2">
                                                    {member.role && (
                                                        <Badge variant="secondary">{ROLE_LABELS[member.role] ?? member.role}</Badge>
                                                    )}
                                                    {member.status !== 'active' && <Badge variant="warning">{member.status}</Badge>}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="subscription" className="space-y-4">
                        {can.manage && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Assign plan</CardTitle>
                                    <CardDescription>Apply a catalogue plan immediately via the manual gateway.</CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-wrap items-end gap-3">
                                    <div className="space-y-1.5">
                                        <Label>Plan</Label>
                                        <Select value={plan} onValueChange={setPlan}>
                                            <SelectTrigger className="w-48">
                                                <SelectValue placeholder="Choose a plan" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {Object.entries(plans).map(([slug, name]) => (
                                                    <SelectItem key={slug} value={slug}>
                                                        {name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>Interval</Label>
                                        <Select value={interval} onValueChange={setInterval}>
                                            <SelectTrigger className="w-36">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="monthly">Monthly</SelectItem>
                                                <SelectItem value="yearly">Yearly</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <Button onClick={assignPlan} disabled={!plan || savingPlan}>
                                        {savingPlan ? 'Applying…' : 'Apply plan'}
                                    </Button>
                                    <Button variant="outline" onClick={extendTrial}>
                                        Extend trial +14d
                                    </Button>
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle>Subscription history</CardTitle>
                                <CardDescription>Recent plan changes for this tenant.</CardDescription>
                            </CardHeader>
                            <CardContent className="p-0">
                                {subscriptions.length === 0 ? (
                                    <p className="px-6 pb-6 text-sm text-muted-foreground">No subscriptions yet.</p>
                                ) : (
                                    <div className="divide-y divide-border">
                                        {subscriptions.map((subscription) => (
                                            <div key={subscription.id} className="flex items-center justify-between gap-3 px-6 py-3 text-sm">
                                                <div>
                                                    <p className="font-medium">{subscription.plan}</p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {subscription.interval} · {subscription.gateway}
                                                        {subscription.current_period_end
                                                            ? ` · renews ${formatDate(subscription.current_period_end)}`
                                                            : ''}
                                                    </p>
                                                </div>
                                                <Badge variant={STATUS_VARIANT[subscription.status] ?? 'secondary'}>
                                                    {subscription.status.replace('_', ' ')}
                                                </Badge>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="usage">
                        <Card>
                            <CardHeader>
                                <CardTitle>Plan usage</CardTitle>
                                <CardDescription>Current consumption against plan limits.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-5">
                                {meters.length === 0 && <p className="text-sm text-muted-foreground">No plan limits to show.</p>}
                                {meters.map((meter) => (
                                    <div key={meter.key} className="space-y-2">
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="font-medium">{meter.label}</span>
                                            <span className="text-muted-foreground">{meterLabel(meter)}</span>
                                        </div>
                                        {meter.percentage !== null && <Progress value={Math.min(100, meter.percentage)} />}
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </AdminLayout>
    );
}
