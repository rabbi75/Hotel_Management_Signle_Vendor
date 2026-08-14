import { BrandLogo } from '@/components/app-shell/brand-logo';
import { ThemeToggle } from '@/components/app-shell/theme-toggle';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useFlashToasts } from '@/hooks/use-flash-toasts';
import { Head } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import type { ReactNode } from 'react';

export interface AdminAuthLayoutProps {
    title: string;
    description?: ReactNode;
    footer?: ReactNode;
    children: ReactNode;
}

/**
 * The console's sign-in shell — deliberately distinct from the tenant AuthLayout
 * (shield mark, cooler wash) so an operator always knows they are logging into
 * the admin world, not the app.
 */
export function AdminAuthLayout({ title, description, footer, children }: AdminAuthLayoutProps) {
    useFlashToasts();

    return (
        <>
            <Head title={`${title} · Console`} />

            <div className="relative flex min-h-svh flex-col items-center justify-center overflow-hidden px-4 py-12">
                <div aria-hidden="true" className="pointer-events-none absolute inset-0 -z-10">
                    <div className="absolute -top-40 left-1/2 size-[36rem] -translate-x-1/2 rounded-full bg-primary/15 blur-3xl" />
                    <div className="absolute -bottom-48 -right-24 size-[28rem] rounded-full bg-chart-3/10 blur-3xl" />
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_top,transparent_35%,var(--background))]" />
                </div>

                <div className="absolute top-4 right-4">
                    <ThemeToggle />
                </div>

                <div className="w-full max-w-sm space-y-6">
                    <div className="flex items-center justify-center">
                        <BrandLogo
                            variant="full"
                            fallbackIcon={ShieldCheck}
                            markClassName="size-9 [&>svg]:size-5"
                            imageClassName="h-9"
                            nameClassName="text-lg font-semibold tracking-tight"
                        />
                    </div>

                    <Card className="shadow-lg">
                        <CardHeader>
                            <CardTitle className="text-xl">{title}</CardTitle>
                            {description && <CardDescription>{description}</CardDescription>}
                        </CardHeader>
                        <CardContent>{children}</CardContent>
                    </Card>

                    {footer && <div className="text-center text-sm text-muted-foreground">{footer}</div>}
                </div>
            </div>
        </>
    );
}

export default AdminAuthLayout;
