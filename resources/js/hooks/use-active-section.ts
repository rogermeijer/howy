import { useEffect, useState } from 'react';

/**
 * The id of the section the reader is in: the last of `ids` whose top has
 * passed `offset` px from the top of the viewport (room for a sticky header).
 * At the very bottom of the page the last id wins, even if its top never got
 * that far. Pass a stable array (a module constant), not a fresh one.
 */
export function useActiveSection(
    ids: readonly string[],
    offset = 120,
): string | null {
    const [active, setActive] = useState<string | null>(null);

    useEffect(() => {
        const update = () => {
            let current: string | null = null;

            for (const id of ids) {
                const element = document.getElementById(id);

                if (element && element.getBoundingClientRect().top <= offset) {
                    current = id;
                }
            }

            const atBottom =
                window.innerHeight + window.scrollY >=
                document.documentElement.scrollHeight - 2;

            setActive(atBottom ? (ids.at(-1) ?? current) : current);
        };

        update();
        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);

        return () => {
            window.removeEventListener('scroll', update);
            window.removeEventListener('resize', update);
        };
    }, [ids, offset]);

    return active;
}
