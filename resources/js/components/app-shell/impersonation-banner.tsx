import { Button } from '@/components/ui/button';
import type { SharedProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { LogOut, VenetianMask } from 'lucide-react';

/**
 * The always-visible reminder that the current session is an impersonation.
 *
 * Rendered by AppLayout above everything else. It is the only affordance for
 * leaving, so it must never be dismissable — a hidden banner is how an operator
 * forgets they are signed in as a customer.
 */
export function ImpersonationBanner() {
    const { auth } = usePage<SharedProps>().props;
    const impersonator = auth.impersonator;

    if (!impersonator) {
        return null;
    }

    function stop(): void {
        router.delete(route('users.impersonate.stop'));
    }

    return (
        <div className="flex items-center justify-center gap-3 bg-warning px-4 py-2 text-sm text-warning-foreground">
            <VenetianMask className="size-4 shrink-0" aria-hidden="true" />
            <span className="min-w-0 truncate">
                Viewing as <strong>{auth.user?.name}</strong> — signed in by {impersonator.name}.
            </span>
            <Button
                size="sm"
                variant="outline"
                onClick={stop}
                className="shrink-0 border-warning-foreground/30 bg-transparent text-warning-foreground hover:bg-warning-foreground/10"
            >
                <LogOut className="size-4" />
                Return to admin
            </Button>
        </div>
    );
}
