import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { usePermissions } from '@/hooks/use-permissions';
import { useEntitlements } from '@/hooks/use-entitlements';
import { useGenerationStream } from '@/pages/ai/use-generation-stream';
import { Check, Copy, Sparkles, Square } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';

export type HotelAiAction =
    | 'reservation.staff_brief'
    | 'reservation.draft_confirmation'
    | 'reservation.draft_pre_arrival'
    | 'guest.stay_summary'
    | 'guest.draft_welcome'
    | 'guest.vip_hints'
    | 'maintenance.triage'
    | 'housekeeping.floor_readiness'
    | 'gm.daily_brief'
    | 'room_type.marketing_copy'
    | 'hotel.booking_copy';

export interface AiAssistButtonProps {
    action: HotelAiAction;
    label?: string;
    description?: string;
    subjectId?: number | string | null;
    hotelId?: number | null;
    focus?: 'description' | 'policies';
    draft?: Record<string, unknown>;
    onApply?: (text: string) => void;
    applyLabel?: string;
    variant?: 'default' | 'outline' | 'ghost' | 'secondary';
    size?: 'default' | 'sm' | 'lg' | 'icon';
    className?: string;
    children?: ReactNode;
}

/**
 * Inline hotel AI assist: opens a sheet, streams through `ai.assist.stream`,
 * and optionally applies the result into a form field.
 */
export function AiAssistButton({
    action,
    label = 'Ask AI',
    description = 'Uses your workspace AI credits and the hotel prompt pack.',
    subjectId = null,
    hotelId = null,
    focus,
    draft,
    onApply,
    applyLabel = 'Use this text',
    variant = 'outline',
    size = 'sm',
    className,
    children,
}: AiAssistButtonProps) {
    const { can } = usePermissions();
    const { hasFeature } = useEntitlements();

    const [open, setOpen] = useState(false);
    const [copied, setCopied] = useState(false);
    const stream = useGenerationStream(route('ai.assist.stream'));

    useEffect(() => {
        if (!open) {
            stream.reset();
            setCopied(false);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps -- reset only when the sheet closes
    }, [open]);

    if (!hasFeature('ai') || !can('ai.use')) {
        return null;
    }

    async function run(): Promise<void> {
        setCopied(false);
        await stream.start({
            action,
            subject_id: subjectId ?? undefined,
            hotel_id: hotelId ?? undefined,
            focus,
            draft,
        });
    }

    async function copy(): Promise<void> {
        if (!stream.output) {
            return;
        }

        await navigator.clipboard.writeText(stream.output);
        setCopied(true);
    }

    return (
        <>
            <Button
                type="button"
                variant={variant}
                size={size}
                className={className}
                onClick={() => {
                    setOpen(true);
                    void run();
                }}
            >
                {children ?? (
                    <>
                        <Sparkles className="size-3.5" aria-hidden="true" />
                        {label}
                    </>
                )}
            </Button>

            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent side="right" className="flex w-full flex-col sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>{label}</SheetTitle>
                        <SheetDescription>{description}</SheetDescription>
                    </SheetHeader>

                    <div className="flex-1 space-y-3 overflow-y-auto px-6 pb-2">
                        {stream.error && <p className="text-sm text-destructive">{stream.error}</p>}
                        <pre className="min-h-48 whitespace-pre-wrap rounded-md border bg-muted/40 p-3 text-sm leading-relaxed">
                            {stream.output || (stream.streaming ? 'Generating…' : 'Waiting for a result.')}
                        </pre>
                    </div>

                    <SheetFooter>
                        <div className="flex flex-wrap gap-2">
                            {stream.streaming ? (
                                <Button type="button" variant="outline" onClick={stream.stop}>
                                    <Square className="size-3.5" aria-hidden="true" />
                                    Stop
                                </Button>
                            ) : (
                                <Button type="button" variant="outline" onClick={() => void run()}>
                                    <Sparkles className="size-3.5" aria-hidden="true" />
                                    Regenerate
                                </Button>
                            )}
                            <Button type="button" variant="outline" disabled={!stream.output} onClick={() => void copy()}>
                                {copied ? <Check className="size-3.5" aria-hidden="true" /> : <Copy className="size-3.5" aria-hidden="true" />}
                                {copied ? 'Copied' : 'Copy'}
                            </Button>
                            {onApply && (
                                <Button
                                    type="button"
                                    disabled={!stream.output}
                                    onClick={() => {
                                        onApply(stream.output);
                                        setOpen(false);
                                    }}
                                >
                                    {applyLabel}
                                </Button>
                            )}
                        </div>
                    </SheetFooter>
                </SheetContent>
            </Sheet>
        </>
    );
}

export default AiAssistButton;
