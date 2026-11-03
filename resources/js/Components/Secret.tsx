import { useSyncExternalStore } from 'react';
import type { ReactNode } from 'react';

/**
 * "Sembunyikan nominal": a client-side toggle shared by every page. It starts from the owner's saved
 * default (Pengaturan) once per page load, then follows the header toggle until a reload.
 */
let hidden: boolean | null = null;
const listeners = new Set<() => void>();

export function initHidden(defaultValue: boolean) {
    if (hidden === null) hidden = defaultValue;
}

export function toggleHidden() {
    hidden = !hidden;
    listeners.forEach((l) => l());
}

export function useHidden(): boolean {
    return useSyncExternalStore(
        (cb) => { listeners.add(cb); return () => listeners.delete(cb); },
        () => hidden ?? false,
        () => false,
    );
}

export default function Secret({ children }: { children: ReactNode }) {
    return useHidden() ? <span aria-label="disembunyikan">••••••</span> : <>{children}</>;
}
