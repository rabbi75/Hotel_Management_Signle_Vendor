import { AiAssistButton } from '@/components/ai/ai-assist-button';
import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { GuestRow, ReservationRow } from '@/types/reservation';
import { Link } from '@inertiajs/react';
import { ArrowLeft, Pencil } from 'lucide-react';

interface Props {
    guest: GuestRow;
    reservations: ReservationRow[];
    can: { update: boolean; delete: boolean };
}

export default function GuestsShow({ guest, reservations, can }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Guests', href: route('guests.index') },
        { label: guest.full_name },
    ];

    return (
        <AppLayout title={guest.full_name} breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-3xl space-y-6">
                <PageHeader
                    title={guest.full_name}
                    description={[guest.email, guest.phone].filter(Boolean).join(' · ') || 'Guest profile'}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <AiAssistButton
                                action="guest.stay_summary"
                                label="Stay summary"
                                subjectId={guest.id}
                                description="Summarise profile and stay history for staff."
                            />
                            <AiAssistButton
                                action="guest.draft_welcome"
                                label="Welcome note"
                                subjectId={guest.id}
                            />
                            <AiAssistButton
                                action="guest.vip_hints"
                                label="VIP / risk hints"
                                subjectId={guest.id}
                            />
                            <Button asChild variant="outline">
                                <Link href={route('guests.index')}>
                                    <ArrowLeft className="size-4" aria-hidden="true" />
                                    Back
                                </Link>
                            </Button>
                            {can.update && (
                                <Button asChild>
                                    <Link href={route('guests.edit', guest.uuid)}>
                                        <Pencil className="size-4" aria-hidden="true" />
                                        Edit
                                    </Link>
                                </Button>
                            )}
                        </div>
                    }
                />
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-3">
                        <CardTitle>Profile</CardTitle>
                        <div className="flex gap-2">
                            {guest.is_vip && <Badge variant="outline">VIP</Badge>}
                            {guest.is_blacklisted && <Badge variant="destructive">Blacklisted</Badge>}
                        </div>
                    </CardHeader>
                    <CardContent className="grid gap-4 text-sm sm:grid-cols-2">
                        <div>
                            <p className="text-muted-foreground">Nationality</p>
                            <p>{guest.nationality || '—'}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">ID</p>
                            <p>{guest.id_type ? `${guest.id_type}: ${guest.id_number || '—'}` : guest.id_number || '—'}</p>
                        </div>
                        <div className="sm:col-span-2">
                            <p className="text-muted-foreground">Address</p>
                            <p>{[guest.address, guest.city, guest.country].filter(Boolean).join(', ') || '—'}</p>
                        </div>
                        {guest.notes && (
                            <div className="sm:col-span-2">
                                <p className="text-muted-foreground">Notes</p>
                                <p>{guest.notes}</p>
                            </div>
                        )}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Stay history</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {reservations.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No reservations yet.</p>
                        ) : (
                            reservations.map((r) => (
                                <Link
                                    key={r.id}
                                    href={route('reservations.show', r.id)}
                                    className="flex flex-wrap items-center justify-between gap-2 rounded-md border px-3 py-2 text-sm hover:bg-muted/40"
                                >
                                    <span className="font-medium">{r.number}</span>
                                    <span className="text-muted-foreground">
                                        {r.check_in_date} → {r.check_out_date}
                                    </span>
                                    <Badge variant="outline">{r.status_label}</Badge>
                                </Link>
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
