import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it } from 'vitest';
import { ConfirmDialogHost } from './confirm-dialog';
import { confirm, useConfirmStore, type ConfirmOptions } from './use-confirm';

function open(options: Partial<ConfirmOptions> = {}): Promise<boolean> {
    return confirm({ title: 'Delete project?', ...options });
}

beforeEach(() => {
    useConfirmStore.setState({ request: null });
});

describe('useConfirm', () => {
    it('renders nothing until a confirmation is requested', () => {
        render(<ConfirmDialogHost />);

        expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument();
    });

    it('resolves true when the action is confirmed', async () => {
        const user = userEvent.setup();
        render(<ConfirmDialogHost />);

        let result: Promise<boolean> | null = null;
        act(() => {
            result = open({ description: 'This removes every task inside it.' });
        });

        expect(await screen.findByText('Delete project?')).toBeInTheDocument();
        expect(screen.getByText('This removes every task inside it.')).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Confirm' }));

        await expect(result).resolves.toBe(true);
    });

    it('resolves false when the action is cancelled', async () => {
        const user = userEvent.setup();
        render(<ConfirmDialogHost />);

        let result: Promise<boolean> | null = null;
        act(() => {
            result = open();
        });

        await screen.findByRole('alertdialog');
        await user.click(screen.getByRole('button', { name: 'Cancel' }));

        await expect(result).resolves.toBe(false);
    });

    it('closes the dialog once the promise settles', async () => {
        const user = userEvent.setup();
        render(<ConfirmDialogHost />);

        act(() => {
            void open();
        });

        await screen.findByRole('alertdialog');
        await user.click(screen.getByRole('button', { name: 'Cancel' }));

        await waitFor(() => expect(useConfirmStore.getState().request).toBeNull());
    });

    it('labels the destructive action Delete by default', async () => {
        render(<ConfirmDialogHost />);

        act(() => {
            void open({ variant: 'destructive' });
        });

        expect(await screen.findByRole('button', { name: 'Delete' })).toBeInTheDocument();
    });

    describe('with a confirmation word', () => {
        it('keeps the action disabled until the word is typed exactly', async () => {
            const user = userEvent.setup();
            render(<ConfirmDialogHost />);

            let result: Promise<boolean> | null = null;
            act(() => {
                result = open({ variant: 'destructive', confirmWord: 'acme-inc' });
            });

            const action = await screen.findByRole('button', { name: 'Delete' });
            expect(action).toBeDisabled();

            const input = screen.getByRole('textbox');
            await user.type(input, 'acme');
            expect(action).toBeDisabled();

            await user.type(input, '-inc');
            await waitFor(() => expect(action).toBeEnabled());

            await user.click(action);
            await expect(result).resolves.toBe(true);
        });

        it('matches the word case-insensitively and ignores surrounding space', async () => {
            const user = userEvent.setup();
            render(<ConfirmDialogHost />);

            act(() => {
                void open({ variant: 'destructive', confirmWord: 'delete' });
            });

            const action = await screen.findByRole('button', { name: 'Delete' });
            await user.type(screen.getByRole('textbox'), '  DELETE  ');

            await waitFor(() => expect(action).toBeEnabled());
        });

        it('warns that the action cannot be undone', async () => {
            render(<ConfirmDialogHost />);

            act(() => {
                void open({ variant: 'destructive', confirmWord: 'delete' });
            });

            expect(await screen.findByText('This action cannot be undone.')).toBeInTheDocument();
        });
    });

    it('resolves a superseded request as false rather than leaving it pending', async () => {
        render(<ConfirmDialogHost />);

        let first: Promise<boolean> | null = null;
        let second: Promise<boolean> | null = null;

        act(() => {
            first = open({ title: 'First' });
            second = open({ title: 'Second' });
        });

        await expect(first).resolves.toBe(false);
        expect(await screen.findByText('Second')).toBeInTheDocument();
        expect(second).toBeInstanceOf(Promise);
    });
});
