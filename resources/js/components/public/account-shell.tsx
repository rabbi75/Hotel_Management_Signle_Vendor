import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { PublicMenuNode } from '@/types/cms';
import { Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { PublicShell } from '@/pages/cms/public-shell';

interface AccountShellProps {
    title: string;
    description?: string;
    customerName: string;
    menus: { header: PublicMenuNode[]; footer: PublicMenuNode[] };
    children: ReactNode;
}

const links = [
    { href: 'account.dashboard', label: 'Overview' },
    { href: 'account.bookings', label: 'My bookings' },
    { href: 'account.profile', label: 'Profile' },
];

export function AccountShell({ title, description, customerName, menus, children }: AccountShellProps) {
    return (
        <PublicShell header={menus.header} footer={menus.footer}>
            <div className="mx-auto w-full max-w-6xl px-4 py-10 sm:px-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">Guest account</p>
                        <h1 className="mt-1 text-3xl font-semibold tracking-tight">{title}</h1>
                        {description && <p className="mt-2 max-w-2xl text-muted-foreground">{description}</p>}
                    </div>
                    <p className="text-sm text-muted-foreground">{customerName}</p>
                </div>

                <div className="mt-8 flex flex-wrap gap-2 border-b border-border pb-4">
                    {links.map((item) => (
                        <Button key={item.href} asChild variant="ghost" size="sm">
                            <Link href={route(item.href)} className={cn(route().current(item.href) && 'bg-muted')}>
                                {item.label}
                            </Link>
                        </Button>
                    ))}
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="ml-auto"
                        onClick={() => router.post(route('account.logout'))}
                    >
                        Sign out
                    </Button>
                </div>

                <div className="mt-8">{children}</div>
            </div>
        </PublicShell>
    );
}
