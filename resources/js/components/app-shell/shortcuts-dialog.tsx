import { Kbd } from '@/components/ui/kbd';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { useShortcutHintList } from '@/hooks/use-command-registry';
import type { ShortcutHint } from '@/stores/command-store';
import { useUiStore } from '@/stores/ui-store';
import { Fragment, useMemo } from 'react';

function group(hints: ShortcutHint[]): [string, ShortcutHint[]][] {
    const groups = new Map<string, ShortcutHint[]>();

    for (const hint of hints) {
        const bucket = groups.get(hint.group);

        if (bucket) {
            bucket.push(hint);

            continue;
        }

        groups.set(hint.group, [hint]);
    }

    return [...groups.entries()];
}

export function ShortcutsDialog() {
    const open = useUiStore((state) => state.shortcutsOpen);
    const setOpen = useUiStore((state) => state.setShortcutsOpen);
    const hints = useShortcutHintList();

    const grouped = useMemo(() => group(hints), [hints]);

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetContent side="right" className="w-full sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>Keyboard shortcuts</SheetTitle>
                    <SheetDescription>Everything you can do without reaching for the mouse.</SheetDescription>
                </SheetHeader>

                <ScrollArea className="mt-4 max-h-[calc(100vh-9rem)] pr-2">
                    {grouped.length === 0 && <p className="text-sm text-muted-foreground">No shortcuts are registered on this page.</p>}

                    <div className="space-y-6">
                        {grouped.map(([name, items]) => (
                            <section key={name} aria-labelledby={`shortcut-group-${name}`}>
                                <h3
                                    id={`shortcut-group-${name}`}
                                    className="mb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                >
                                    {name}
                                </h3>
                                <dl className="divide-y divide-border rounded-lg border border-border">
                                    {items.map((hint) => (
                                        <div key={`${name}-${hint.label}`} className="flex items-center justify-between gap-4 px-3 py-2">
                                            <dt className="text-sm">{hint.label}</dt>
                                            <dd className="flex shrink-0 items-center gap-1">
                                                {hint.keys.map((key, index) => (
                                                    <Fragment key={key}>
                                                        {index > 0 && <span className="text-xs text-muted-foreground">then</span>}
                                                        <Kbd>{key}</Kbd>
                                                    </Fragment>
                                                ))}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                            </section>
                        ))}
                    </div>
                </ScrollArea>
            </SheetContent>
        </Sheet>
    );
}
