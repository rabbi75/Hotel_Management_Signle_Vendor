import { toast } from '@/components/ui/toast';
import type { SharedProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

type FlashLevel = keyof SharedProps['flash'];

const LEVELS: FlashLevel[] = ['success', 'error', 'warning', 'info'];

/**
 * Raises a toast for each flash message the server sends.
 *
 * Inertia keeps the same props object across a `preserveState` reload, so the
 * previous payload is compared by value; keying on identity alone would replay
 * a message every time an unrelated partial reload landed.
 */
export function useFlashToasts(): void {
    const { flash } = usePage<SharedProps>().props;
    const previous = useRef<string>('');

    useEffect(() => {
        const signature = LEVELS.map((level) => `${level}:${flash?.[level] ?? ''}`).join('|');

        if (signature === previous.current) {
            return;
        }

        previous.current = signature;

        for (const level of LEVELS) {
            const message = flash?.[level];

            if (message) {
                toast[level](message);
            }
        }
    }, [flash]);
}
