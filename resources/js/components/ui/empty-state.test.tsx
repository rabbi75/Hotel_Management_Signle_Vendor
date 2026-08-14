import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { render, screen } from '@testing-library/react';
import { Inbox } from 'lucide-react';
import { describe, expect, it } from 'vitest';

describe('EmptyState', () => {
    it('renders the title and description', () => {
        render(<EmptyState title="No results" description="Try a different search." />);

        expect(screen.getByText('No results')).toBeInTheDocument();
        expect(screen.getByText('Try a different search.')).toBeInTheDocument();
    });

    it('renders the icon when provided', () => {
        const { container } = render(<EmptyState icon={Inbox} title="No messages" />);

        expect(container.querySelector('svg')).toBeInTheDocument();
    });

    it('omits the icon wrapper when no icon is provided', () => {
        const { container } = render(<EmptyState title="No messages" />);

        expect(container.querySelector('svg')).not.toBeInTheDocument();
    });

    it('renders the action slot when provided', () => {
        render(<EmptyState title="No messages" action={<Button>Create message</Button>} />);

        expect(screen.getByRole('button', { name: 'Create message' })).toBeInTheDocument();
    });
});
