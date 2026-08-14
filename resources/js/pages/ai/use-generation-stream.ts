import type { AiGenerationRow } from '@/types/ai';
import { useCallback, useEffect, useRef, useState } from 'react';

export interface StreamPayload {
    prompt?: string;
    system?: string | null;
    provider?: string | null;
    model?: string | null;
    max_tokens?: number | null;
    template_id?: number | null;
    variables?: Record<string, string>;
    /** Hotel assist fields — used by `ai.assist.stream`. */
    action?: string;
    subject_id?: number | string;
    hotel_id?: number;
    focus?: string;
    draft?: Record<string, unknown>;
}

export interface GenerationStreamState {
    /** Text accumulated so far. Present even after a refusal or a truncation. */
    output: string;
    streaming: boolean;
    error: string | null;
    /** The persisted record, available once the stream has closed cleanly. */
    generation: AiGenerationRow | null;
    start: (payload: StreamPayload) => Promise<void>;
    stop: () => void;
    reset: () => void;
}

/** Laravel's session cookie CSRF token, which `fetch` will not attach for us. */
function csrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match?.[1] ? decodeURIComponent(match[1]) : '';
}

interface SseFrame {
    event: string;
    data: string;
}

/** Splits a raw SSE buffer into complete frames, returning the unconsumed tail. */
function parseFrames(buffer: string): { frames: SseFrame[]; rest: string } {
    const frames: SseFrame[] = [];
    const chunks = buffer.split('\n\n');

    // The final chunk is whatever arrived after the last blank line; it may be
    // a partial frame, so it is carried over rather than parsed.
    const rest = chunks.pop() ?? '';

    for (const chunk of chunks) {
        let event = 'message';
        let data = '';

        for (const line of chunk.split('\n')) {
            if (line.startsWith('event:')) {
                event = line.slice(6).trim();
            } else if (line.startsWith('data:')) {
                data += line.slice(5).trim();
            }
        }

        if (data !== '') {
            frames.push({ event, data });
        }
    }

    return { frames, rest };
}

/**
 * Drives one server-sent-events generation.
 *
 * Streaming rather than a blocking POST is what keeps a long generation from
 * dying to an HTTP timeout, and it is also what makes the stop button possible:
 * aborting the fetch closes the connection, and the server settles the credit
 * reservation from whatever was produced up to that point.
 */
export function useGenerationStream(streamUrl: string): GenerationStreamState {
    const [output, setOutput] = useState('');
    const [streaming, setStreaming] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [generation, setGeneration] = useState<AiGenerationRow | null>(null);

    const controller = useRef<AbortController | null>(null);

    useEffect(
        () => () => {
            controller.current?.abort();
        },
        [],
    );

    const stop = useCallback(() => {
        controller.current?.abort();
        controller.current = null;
        setStreaming(false);
    }, []);

    const reset = useCallback(() => {
        setOutput('');
        setError(null);
        setGeneration(null);
    }, []);

    const start = useCallback(
        async (payload: StreamPayload) => {
            controller.current?.abort();

            const abort = new AbortController();
            controller.current = abort;

            setOutput('');
            setError(null);
            setGeneration(null);
            setStreaming(true);

            try {
                const response = await fetch(streamUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    signal: abort.signal,
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'text/event-stream',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-XSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify(payload),
                });

                if (!response.ok || !response.body) {
                    const problem = (await response.json().catch(() => null)) as { message?: string } | null;

                    throw new Error(problem?.message ?? `The provider request failed (HTTP ${response.status}).`);
                }

                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';

                for (;;) {
                    const { done, value } = await reader.read();

                    if (done) {
                        break;
                    }

                    buffer += decoder.decode(value, { stream: true });

                    const { frames, rest } = parseFrames(buffer);
                    buffer = rest;

                    for (const frame of frames) {
                        const parsed: unknown = JSON.parse(frame.data);

                        if (frame.event === 'delta') {
                            const { text } = parsed as { text: string };
                            setOutput((current) => current + text);
                        } else if (frame.event === 'done') {
                            const { generation: record } = parsed as { generation: AiGenerationRow };
                            setGeneration(record);
                        } else if (frame.event === 'error') {
                            const { message } = parsed as { message: string };
                            setError(message);
                        }
                    }
                }
            } catch (exception) {
                // An abort is the user pressing stop, not a failure.
                if (!(exception instanceof DOMException && exception.name === 'AbortError')) {
                    setError(exception instanceof Error ? exception.message : 'The generation could not be completed.');
                }
            } finally {
                controller.current = null;
                setStreaming(false);
            }
        },
        [streamUrl],
    );

    return { output, streaming, error, generation, start, stop, reset };
}
