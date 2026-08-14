import {
    Breadcrumb,
    BreadcrumbEllipsis,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { BreadcrumbItem as BreadcrumbEntry } from '@/types';
import { Link } from '@inertiajs/react';
import { Fragment } from 'react';

const MAX_VISIBLE = 4;

export interface BreadcrumbsProps {
    items: BreadcrumbEntry[];
    className?: string;
}

interface Crumb {
    entry: BreadcrumbEntry;
    isLast: boolean;
}

export function Breadcrumbs({ items, className }: BreadcrumbsProps) {
    if (items.length === 0) {
        return null;
    }

    const collapsed = items.length > MAX_VISIBLE;
    const first = items[0];
    const tail = collapsed ? items.slice(-2) : items.slice(1);
    const hidden = collapsed ? items.slice(1, -2) : [];

    if (!first) {
        return null;
    }

    const trailing: Crumb[] = tail.map((entry, index) => ({ entry, isLast: index === tail.length - 1 }));

    return (
        <Breadcrumb className={className}>
            <BreadcrumbList>
                <BreadcrumbItem>
                    <CrumbContent entry={first} isLast={items.length === 1} />
                </BreadcrumbItem>

                {hidden.length > 0 && (
                    <>
                        <BreadcrumbSeparator />
                        <BreadcrumbItem>
                            <DropdownMenu>
                                <DropdownMenuTrigger
                                    className="flex items-center rounded-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    aria-label={`Show ${hidden.length} hidden breadcrumb levels`}
                                >
                                    <BreadcrumbEllipsis />
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="start">
                                    {hidden.map((entry) => (
                                        <DropdownMenuItem key={`${entry.label}-${entry.href ?? ''}`} asChild={Boolean(entry.href)}>
                                            {entry.href ? <Link href={entry.href}>{entry.label}</Link> : <span>{entry.label}</span>}
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </BreadcrumbItem>
                    </>
                )}

                {trailing.map(({ entry, isLast }) => (
                    <Fragment key={`${entry.label}-${entry.href ?? ''}`}>
                        <BreadcrumbSeparator />
                        <BreadcrumbItem>
                            <CrumbContent entry={entry} isLast={isLast} />
                        </BreadcrumbItem>
                    </Fragment>
                ))}
            </BreadcrumbList>
        </Breadcrumb>
    );
}

function CrumbContent({ entry, isLast }: Crumb) {
    if (isLast || !entry.href) {
        return <BreadcrumbPage>{entry.label}</BreadcrumbPage>;
    }

    return (
        <BreadcrumbLink asChild>
            <Link href={entry.href}>{entry.label}</Link>
        </BreadcrumbLink>
    );
}
