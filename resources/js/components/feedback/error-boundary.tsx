import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { TriangleAlert } from 'lucide-react';
import { Component, type ErrorInfo, type ReactNode } from 'react';

export interface ErrorBoundaryProps {
    children: ReactNode;
    /** Replaces the default panel. Receives the error and a reset callback. */
    fallback?: (error: Error, reset: () => void) => ReactNode;
    onError?: (error: Error, info: ErrorInfo) => void;
}

interface ErrorBoundaryState {
    error: Error | null;
}

/**
 * Catches render-time errors so one broken widget cannot blank the whole shell.
 * Class syntax is required — React exposes no hook equivalent.
 */
export class ErrorBoundary extends Component<ErrorBoundaryProps, ErrorBoundaryState> {
    override state: ErrorBoundaryState = { error: null };

    static getDerivedStateFromError(error: Error): ErrorBoundaryState {
        return { error };
    }

    override componentDidCatch(error: Error, info: ErrorInfo): void {
        this.props.onError?.(error, info);

        console.error('Unhandled error in React tree:', error, info.componentStack);
    }

    reset = (): void => {
        this.setState({ error: null });
    };

    override render(): ReactNode {
        const { error } = this.state;

        if (!error) {
            return this.props.children;
        }

        if (this.props.fallback) {
            return this.props.fallback(error, this.reset);
        }

        return (
            <div role="alert" className="py-10">
                <EmptyState
                    icon={TriangleAlert}
                    title="Something went wrong"
                    description={error.message || 'This section failed to render. Try again, or reload the page.'}
                    action={
                        <div className="flex gap-2">
                            <Button variant="outline" onClick={this.reset}>
                                Try again
                            </Button>
                            <Button onClick={() => window.location.reload()}>Reload page</Button>
                        </div>
                    }
                />
            </div>
        );
    }
}
