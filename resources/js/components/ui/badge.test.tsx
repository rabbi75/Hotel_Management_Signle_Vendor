import { Badge } from '@/components/ui/badge';
import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

describe('Badge', () => {
    it('renders its label', () => {
        render(<Badge>Active</Badge>);

        expect(screen.getByText('Active')).toBeInTheDocument();
    });

    it.each([
        ['default', 'bg-primary'],
        ['secondary', 'bg-secondary'],
        ['success', 'bg-success'],
        ['warning', 'bg-warning'],
        ['destructive', 'bg-destructive'],
        ['info', 'bg-info'],
    ] as const)('applies the %s variant class', (variant, expectedClass) => {
        render(<Badge variant={variant}>Status</Badge>);

        expect(screen.getByText('Status').className).toContain(expectedClass);
    });

    it('renders as the child element when asChild is set', () => {
        render(
            <Badge asChild>
                <a href="/status">Status</a>
            </Badge>,
        );

        const link = screen.getByRole('link', { name: 'Status' });

        expect(link.tagName).toBe('A');
    });
});
