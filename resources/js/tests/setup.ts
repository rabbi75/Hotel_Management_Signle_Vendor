import '@testing-library/jest-dom/vitest';

class MockResizeObserver implements ResizeObserver {
    observe(): void {}
    unobserve(): void {}
    disconnect(): void {}
}

if (typeof window !== 'undefined') {
    if (!window.ResizeObserver) {
        window.ResizeObserver = MockResizeObserver;
    }

    if (!window.matchMedia) {
        window.matchMedia = (query: string): MediaQueryList => ({
            matches: false,
            media: query,
            onchange: null,
            addListener: () => {},
            removeListener: () => {},
            addEventListener: () => {},
            removeEventListener: () => {},
            dispatchEvent: () => false,
        });
    }
}
