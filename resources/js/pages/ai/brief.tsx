import { AiAssistButton } from '@/components/ai/ai-assist-button';
import { PageHeader } from '@/components/app-shell/page-header';
import { routeUrl } from '@/components/app-shell/routing';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { AppLayout } from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { AiCreditSummary } from '@/types/ai';
import { Link } from '@inertiajs/react';
import { Info, Sparkles } from 'lucide-react';
import { useState } from 'react';

interface BriefAction {
    action: 'gm.daily_brief' | 'housekeeping.floor_readiness';
    label: string;
    description: string;
}

interface PackTemplate {
    slug: string;
    name: string;
    description: string;
}

interface Props {
    actions: BriefAction[];
    templates: PackTemplate[];
    hotels: Record<string, string>;
    selected_hotel_id: number | null;
    default_provider: string;
    credits: AiCreditSummary;
}

export default function AiBriefPage({ actions, templates, hotels, selected_hotel_id, credits }: Props) {
    const [hotelId, setHotelId] = useState<number | null>(selected_hotel_id);
    const breadcrumbs: BreadcrumbItem[] = [
        { label: 'Dashboard', href: routeUrl('dashboard') ?? undefined },
        { label: 'Assistants' },
        { label: 'Daily brief' },
    ];

    return (
        <AppLayout title="Daily brief" breadcrumbs={breadcrumbs}>
            <div className="mx-auto max-w-4xl space-y-6">
                <PageHeader
                    title="Daily brief"
                    description="Generate shift and GM narratives from today's hotel snapshot. Inline assists also appear on reservations, guests, maintenance, and room types."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button asChild variant="outline">
                                <Link href={route('ai.templates.index')}>Templates</Link>
                            </Button>
                            <Button asChild variant="outline">
                                <Link href={route('ai.credits.index')}>Usage</Link>
                            </Button>
                            <Button asChild variant="outline">
                                <Link href={route('ai.index')}>
                                    <Sparkles className="size-4" aria-hidden="true" />
                                    Playground
                                </Link>
                            </Button>
                        </div>
                    }
                />

                <Alert>
                    <Info aria-hidden="true" />
                    <AlertDescription>
                        {credits.enabled
                            ? `${credits.available} of ${credits.allowance} credits left this period (${credits.period}).`
                            : 'AI credits are disabled for this installation.'}{' '}
                        Hotel prompt pack: {templates.length} templates ready.
                    </AlertDescription>
                </Alert>

                {Object.keys(hotels).length > 0 && (
                    <div className="max-w-xs space-y-2">
                        <p className="text-sm font-medium">Hotel context</p>
                        <Select
                            value={hotelId ? String(hotelId) : '__all__'}
                            onValueChange={(value) => setHotelId(value === '__all__' ? null : Number(value))}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select hotel" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__all__">All hotels</SelectItem>
                                {Object.entries(hotels).map(([id, name]) => (
                                    <SelectItem key={id} value={id}>
                                        {name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                )}

                <div className="grid gap-4 md:grid-cols-2">
                    {actions.map((item) => (
                        <Card key={item.action}>
                            <CardHeader>
                                <CardTitle className="text-base">{item.label}</CardTitle>
                                <CardDescription>{item.description}</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <AiAssistButton
                                    action={item.action}
                                    label={`Generate ${item.label.toLowerCase()}`}
                                    hotelId={hotelId}
                                    description={item.description}
                                />
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Hotel prompt pack</CardTitle>
                        <CardDescription>Shared templates installed for this workspace. Edit them under Templates if needed.</CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-2">
                        {templates.map((template) => (
                            <Badge key={template.slug} variant="outline" title={template.description}>
                                {template.name}
                            </Badge>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
