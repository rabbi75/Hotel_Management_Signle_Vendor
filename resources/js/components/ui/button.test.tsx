import { Button } from '@/components/ui/button';
import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

describe('Button', () => {
    it('renders children', () => {
        render(<Button>Save changes</Button>);

        expect(screen.getByRole('button', { name: 'Save changes' })).toBeInTheDocument();
    });

    it('disables and shows a spinner when loading', () => {
        render(<Button loading>Save changes</Button>);

        const button = screen.getByRole('button');

        expect(button).toBeDisabled();
        expect(button).toHaveAttribute('aria-busy', 'true');
        expect(button.querySelector('svg')).toBeInTheDocument();
    });

    it('renders the child element in place of a button when asChild is set', () => {
        render(
            <Button asChild>
                <a href="/billing">Billing</a>
            </Button>,
        );

        const link = screen.getByRole('link', { name: 'Billing' });

        expect(link).toBeInTheDocument();
        expect(link.tagName).toBe('A');
        expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });

    it('applies variant and size classes', () => {
        render(
            <Button variant="destructive" size="lg">
                Delete
            </Button>,
        );

        const button = screen.getByRole('button', { name: 'Delete' });

        expect(button.className).toContain('bg-destructive');
        expect(button.className).toContain('h-10');
    });
});
