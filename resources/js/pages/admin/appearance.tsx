import { PageHeader } from '@/components/app-shell/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { AdminLayout } from '@/layouts/admin-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import { router } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useState, type CSSProperties } from 'react';

/*
|------------------------------------------------------------------------------
| Panel appearance
|------------------------------------------------------------------------------
|
| The two sidebars are themed independently, and only from here: an operator
| decides how the product looks, a tenant does not. Presets rather than colour
| pickers, because every one of them ships a checked light *and* dark variant —
| a hex field would let an operator make the rail unreadable in one theme
| without ever seeing it.
|
*/

type Tokens = Record<string, string>;

interface Palette {
    value: string;
    label: string;
    swatch: string;
    light: Tokens;
    dark: Tokens;
}

interface AppearancePageProps {
    palettes: Palette[];
    settings: {
        admin_sidebar_theme: string;
        app_sidebar_theme: string;
    };
}

/** The preset's tokens as inline CSS variables, so the mock previews unsaved. */
function toStyle(tokens: Tokens): CSSProperties {
    const style: Record<string, string> = {};

    for (const [token, value] of Object.entries(tokens)) {
        style[`--${token}`] = value;
    }

    return style as CSSProperties;
}

function SidebarMock({ palette, dark }: { palette: Palette; dark: boolean }) {
    const rows = ['Overview', 'Tenants', 'Pages'];

    return (
        <div
            style={toStyle(dark ? palette.dark : palette.light)}
            className="w-full overflow-hidden rounded-lg border"
            aria-hidden="true"
        >
            <div className="flex flex-col gap-1 bg-sidebar p-2" style={{ borderColor: 'var(--sidebar-border)' }}>
                {rows.map((row, index) => (
                    <span
                        key={row}
                        className={cn(
                            'rounded-md px-2 py-1.5 text-xs font-medium',
                            index === 0 ? 'bg-sidebar-accent text-sidebar-accent-foreground' : 'text-sidebar-foreground/70',
                        )}
                    >
                        {row}
                    </span>
                ))}
            </div>
        </div>
    );
}

function PaletteGrid({
    palettes,
    value,
    onChange,
    name,
}: {
    palettes: Palette[];
    value: string;
    onChange: (next: string) => void;
    name: string;
}) {
    const selected = palettes.find((palette) => palette.value === value) ?? palettes[0];

    return (
        <div className="space-y-4">
            <div role="radiogroup" aria-label={name} className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                {palettes.map((palette) => {
                    const active = palette.value === value;

                    return (
                        <button
                            key={palette.value}
                            type="button"
                            role="radio"
                            aria-checked={active}
                            onClick={() => onChange(palette.value)}
                            className={cn(
                                'flex items-center gap-2 rounded-lg border p-2 text-left transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                active ? 'border-primary bg-primary/5' : 'border-border hover:bg-accent/60',
                            )}
                        >
                            <span
                                className="flex size-6 shrink-0 items-center justify-center rounded-full"
                                style={{ backgroundColor: palette.swatch }}
                            >
                                {active && <Check className="size-3.5 text-white" aria-hidden="true" />}
                            </span>
                            <span className="truncate text-sm font-medium">{palette.label}</span>
                        </button>
                    );
                })}
            </div>

            {selected && (
                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="space-y-1">
                        <p className="text-xs font-medium text-muted-foreground">Light</p>
                        <SidebarMock palette={selected} dark={false} />
                    </div>
                    <div className="space-y-1">
                        <p className="text-xs font-medium text-muted-foreground">Dark</p>
                        <SidebarMock palette={selected} dark />
                    </div>
                </div>
            )}
        </div>
    );
}

export default function AdminAppearancePage({ palettes, settings }: AppearancePageProps) {
    const [adminTheme, setAdminTheme] = useState(settings.admin_sidebar_theme);
    const [appTheme, setAppTheme] = useState(settings.app_sidebar_theme);
    const [saving, setSaving] = useState(false);

    const dirty = adminTheme !== settings.admin_sidebar_theme || appTheme !== settings.app_sidebar_theme;

    const breadcrumbs: BreadcrumbItem[] = [{ label: 'Platform' }, { label: 'Appearance' }];

    function save(): void {
        setSaving(true);

        router.put(
            route('admin.appearance.update'),
            { admin_sidebar_theme: adminTheme, app_sidebar_theme: appTheme },
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
            },
        );
    }

    return (
        <AdminLayout title="Appearance" breadcrumbs={breadcrumbs}>
            <div className="space-y-6">
                <PageHeader
                    title="Appearance"
                    description="The sidebar palette for each panel. Applies to every operator and every workspace."
                    actions={
                        <Button type="button" onClick={save} disabled={!dirty || saving}>
                            {saving ? 'Saving…' : 'Save changes'}
                        </Button>
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Admin panel sidebar</CardTitle>
                        <CardDescription>This console. The change is visible on the next page load.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <PaletteGrid name="Admin panel sidebar" palettes={palettes} value={adminTheme} onChange={setAdminTheme} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Client panel sidebar</CardTitle>
                        <CardDescription>The workspace app every tenant signs in to.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <PaletteGrid name="Client panel sidebar" palettes={palettes} value={appTheme} onChange={setAppTheme} />
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
