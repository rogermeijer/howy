import { useSyncExternalStore } from 'react';

function scrollListener(callback: () => void) {
    window.addEventListener('scroll', callback, { passive: true });

    return () => {
        window.removeEventListener('scroll', callback);
    };
}

function getServerSnapshot(): boolean {
    return false;
}

/**
 * Whether the page has scrolled past `offset` px. Only flips at the
 * threshold, so a sticky header re-renders twice per pass, not per frame.
 */
export function useScrolled(offset = 8): boolean {
    return useSyncExternalStore(
        scrollListener,
        () => window.scrollY > offset,
        getServerSnapshot,
    );
}
