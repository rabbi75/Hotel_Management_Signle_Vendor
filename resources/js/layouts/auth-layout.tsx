import { BrandLogo } from '@/components/app-shell/brand-logo';
import { ThemeToggle } from '@/components/app-shell/theme-toggle';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useFlashToasts } from '@/hooks/use-flash-toasts';
import type { SharedProps } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

export interface AuthLayoutProps {
    title: string;
    description?: ReactNode;
    /** Rendered under the card — the "Don't have an account?" line. */
    footer?: ReactNode;
    children: ReactNode;
}

export function AuthLayout({ title, description, footer, children }: AuthLayoutProps) {
    const { name } = usePage<SharedProps>().props;

    useFlashToasts();

    return (
        <>
            <Head title={title} />

            <div className="relative flex min-h-svh flex-col items-center justify-center overflow-hidden px-4 py-12">
                {/* Two soft, off-centre washes in brand and chart hues; keeps the page
                    from reading as a bare white sheet without competing with the card. */}
                <div aria-hidden="true" className="pointer-events-none absolute inset-0 -z-10">
                    <div className="absolute -top-40 left-1/2 size-[36rem] -translate-x-1/2 rounded-full bg-primary/10 blur-3xl" />
                    <div className="absolute -bottom-48 -left-24 size-[28rem] rounded-full bg-chart-2/10 blur-3xl" />
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_top,transparent_35%,var(--background))]" />
                </div>

                <div className="absolute top-4 right-4">
                    <ThemeToggle />
                </div>

                <div className="w-full max-w-sm space-y-6">
                    <Link href="/" className="flex items-center justify-center rounded-md" aria-label={`${name} home`}>
                        <BrandLogo
                            variant="full"
                            markClassName="size-9 [&>svg]:size-5"
                            imageClassName="h-10 max-w-[16rem]"
                            nameClassName="text-lg"
                        />
                    </Link>

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

export default AuthLayout;
