import { Avatar, AvatarFallback, AvatarGroup } from '@/components/ui/avatar';
import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

function makeAvatar(label: string) {
    return (
        <Avatar key={label}>
            <AvatarFallback>{label}</AvatarFallback>
        </Avatar>
    );
}

describe('AvatarGroup', () => {
    it('renders every avatar when under the max', () => {
        render(<AvatarGroup max={4}>{['AA', 'BB', 'CC'].map(makeAvatar)}</AvatarGroup>);

        expect(screen.getByText('AA')).toBeInTheDocument();
        expect(screen.getByText('BB')).toBeInTheDocument();
        expect(screen.getByText('CC')).toBeInTheDocument();
        expect(screen.queryByText(/^\+/)).not.toBeInTheDocument();
    });

    it('folds anything past max into a +N overflow chip', () => {
        render(<AvatarGroup max={2}>{['AA', 'BB', 'CC', 'DD', 'EE'].map(makeAvatar)}</AvatarGroup>);

        expect(screen.getByText('AA')).toBeInTheDocument();
        expect(screen.getByText('BB')).toBeInTheDocument();
        expect(screen.queryByText('CC')).not.toBeInTheDocument();
        expect(screen.getByText('+3')).toBeInTheDocument();
    });
});
