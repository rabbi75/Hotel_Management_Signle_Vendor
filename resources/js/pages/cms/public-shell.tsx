import { BrandLogo } from '@/components/app-shell/brand-logo';
import { routeUrl } from '@/components/app-shell/routing';
import { ThemeToggle } from '@/components/app-shell/theme-toggle';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types';
import type { PublicMenuNode } from '@/types/cms';
import { Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import type { ReactNode } from 'react';

/*
|------------------------------------------------------------------------------
| The public site chrome
|------------------------------------------------------------------------------
|
| Header, footer and account links for anything a visitor sees: the CMS
| homepage, any published page, and the editor's own preview. One component for
| all three on purpose — a preview that does not match the live page is worse
| than no preview at all.
|
| Every route is resolved through the guarded `routeUrl()` helper, so a kit with
| registration disabled or the auth module removed renders without the link
| rather than throwing out of the render pass.
|
*/

function MenuLinks({ nodes, className }: { nodes: PublicMenuNode[]; className?: string }) {
    if (nodes.length === 0) {
        return null;
    }

    return (
        <ul className={className}>
            {nodes.map((node) => (
                <li key={node.id}>
                    <a
                        href={node.url}
                        target={node.target}
                        rel={node.target === '_blank' ? 'noreferrer' : undefined}
                        className="rounded text-sm text-muted-foreground underline-offset-4 transition-colors hover:text-foreground hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        {node.label}
                    </a>
                </li>
            ))}
        </ul>
    );
}

export interface PublicShellProps {
    children: ReactNode;
    header?: PublicMenuNode[];
    footer?: PublicMenuNode[];
    /** Rendered above the header — the preview banner uses it. */
    banner?: ReactNode;
    className?: string;
}

export function PublicShell({ children, header = [], footer = [], banner, className }: PublicShellProps) {
    const { name, auth, bookingUrl } = usePage<SharedProps>().props;

    const loginUrl = routeUrl('login');
    const registerUrl = routeUrl('register');
    const dashboardUrl = routeUrl('dashboard');
    const authenticated = auth.user !== null;

    return (
        <div className={cn('flex min-h-svh flex-col bg-background text-foreground', className)}>
            {banner}

            <header className="sticky top-0 z-30 border-b border-border/60 bg-background/80 backdrop-blur-md supports-[backdrop-filter]:bg-background/60">
                <div className="mx-auto flex w-full max-w-6xl items-center gap-6 px-4 py-3.5 sm:px-6">
                    <Link
                        href="/"
                        className="flex shrink-0 items-center rounded-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        aria-label={`${name} home`}
                    >
                        <BrandLogo variant="landing" />
                    </Link>

                    <nav aria-label="Primary" className="hidden flex-1 md:block">
                        <MenuLinks nodes={header} className="flex flex-wrap items-center gap-6" />
                    </nav>

                    <div className="ml-auto flex items-center gap-2">
                        <ThemeToggle />

                        {bookingUrl && !authenticated && (
                            <Button asChild size="sm">
                                <Link href={bookingUrl}>Book a room</Link>
                            </Button>
                        )}

                        {authenticated
                            ? dashboardUrl && (
                                  <Button asChild size="sm" variant={bookingUrl ? 'outline' : 'default'}>
                                      <Link href={dashboardUrl}>
                                          Dashboard
                                          <ArrowRight className="size-4" aria-hidden="true" />
                                      </Link>
                                  </Button>
                              )
                            : (
                                  <>
                                      {loginUrl && (
                                          <Button asChild variant="ghost" size="sm">
                                              <Link href={loginUrl}>Log in</Link>
                                          </Button>
                                      )}
                                      {registerUrl && (
                                          <Button asChild size="sm" variant={bookingUrl ? 'outline' : 'default'}>
                                              <Link href={registerUrl}>Get started</Link>
                                          </Button>
                                      )}
                                  </>
                              )}
                    </div>
                </div>

                {header.length > 0 && (
                    <nav aria-label="Primary" className="border-t border-border/60 md:hidden">
                        <div className="mx-auto max-w-6xl px-4 py-2.5 sm:px-6">
                            <MenuLinks nodes={header} className="flex flex-wrap items-center gap-4" />
                        </div>
                    </nav>
                )}
            </header>

            <main id="main-content" className="flex-1">
                {children}
            </main>

            <footer className="border-t border-border">
                <div className="mx-auto flex w-full max-w-6xl flex-col items-center justify-between gap-4 px-4 py-8 text-sm text-muted-foreground sm:flex-row sm:px-6">
                    <p>© {new Date().getFullYear()} {name}. All rights reserved.</p>

                    {footer.length > 0 ? (
                        <nav aria-label="Footer">
                            <MenuLinks nodes={footer} className="flex flex-wrap items-center justify-center gap-6" />
                        </nav>
                    ) : (
                        <p>Gulshan, Dhaka</p>
                    )}
                </div>
            </footer>
        </div>
    );
}
