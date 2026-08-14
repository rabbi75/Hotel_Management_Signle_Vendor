import { CheckCircle2, Info, Loader2, TriangleAlert, XCircle } from 'lucide-react';
import { useEffect, useState, type ComponentProps, type CSSProperties } from 'react';
import { toast as sonnerToast, Toaster as SonnerToaster, type ExternalToast } from 'sonner';

/** The theme is driven by the `.dark` class on `<html>` (see app.css), not by `prefers-color-scheme` alone, so it is tracked directly instead of delegating to sonner's `theme="system"`. */
function useResolvedTheme(): 'light' | 'dark' {
    const [theme, setTheme] = useState<'light' | 'dark'>(() => (document.documentElement.classList.contains('dark') ? 'dark' : 'light'));

    useEffect(() => {
        const root = document.documentElement;
        const update = () => setTheme(root.classList.contains('dark') ? 'dark' : 'light');
        update();

        const observer = new MutationObserver(update);
        observer.observe(root, { attributes: true, attributeFilter: ['class'] });

        return () => observer.disconnect();
    }, []);

    return theme;
}

export function Toaster(props: ComponentProps<typeof SonnerToaster>) {
    const theme = useResolvedTheme();

    return (
        <SonnerToaster
            theme={theme}
            className="toaster group"
            richColors={false}
            style={
                {
                    '--normal-bg': 'var(--popover)',
                    '--normal-text': 'var(--popover-foreground)',
                    '--normal-border': 'var(--border)',
                    '--success-bg': 'var(--popover)',
                    '--success-text': 'var(--success)',
                    '--success-border': 'var(--border)',
                    '--error-bg': 'var(--popover)',
                    '--error-text': 'var(--destructive)',
                    '--error-border': 'var(--border)',
                    '--warning-bg': 'var(--popover)',
                    '--warning-text': 'var(--warning)',
                    '--warning-border': 'var(--border)',
                    '--info-bg': 'var(--popover)',
                    '--info-text': 'var(--info)',
                    '--info-border': 'var(--border)',
                } as CSSProperties
            }
            icons={{
                success: <CheckCircle2 className="size-4 text-success" />,
                error: <XCircle className="size-4 text-destructive" />,
                warning: <TriangleAlert className="size-4 text-warning" />,
                info: <Info className="size-4 text-info" />,
                loading: <Loader2 className="size-4 animate-spin text-muted-foreground" />,
            }}
            {...props}
        />
    );
}

type ToastMessage = Parameters<typeof sonnerToast>[0];

/** Thin, explicitly-typed wrapper over sonner's toast function so call sites never import from `sonner` directly. */
export const toast = Object.assign((message: ToastMessage, data?: ExternalToast) => sonnerToast(message, data), {
    success: (message: ToastMessage, data?: ExternalToast) => sonnerToast.success(message, data),
    error: (message: ToastMessage, data?: ExternalToast) => sonnerToast.error(message, data),
    warning: (message: ToastMessage, data?: ExternalToast) => sonnerToast.warning(message, data),
    info: (message: ToastMessage, data?: ExternalToast) => sonnerToast.info(message, data),
    promise: sonnerToast.promise,
    dismiss: sonnerToast.dismiss,
});
