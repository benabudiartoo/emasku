import { useEffect, useRef } from 'react';
import type { ReactNode } from 'react';
import { X } from '@phosphor-icons/react';

/** Native <dialog>: focus trap, Escape and backdrop come from the browser. */
export default function Modal({ open, onClose, title, children }: { open: boolean; onClose: () => void; title: string; children: ReactNode }) {
    const ref = useRef<HTMLDialogElement>(null);

    useEffect(() => {
        const d = ref.current;
        if (!d) return;
        if (open && !d.open) d.showModal();
        if (!open && d.open) d.close();
    }, [open]);

    return (
        <dialog
            ref={ref}
            onClose={onClose}
            onClick={(e) => { if (e.target === ref.current) onClose(); }}
            aria-label={title}
            className="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl border border-line bg-white p-0 text-ink shadow-xl backdrop:bg-ink/45"
        >
            <div className="flex items-center justify-between border-b border-line px-5 py-3">
                <h2 className="text-base font-semibold">{title}</h2>
                <button type="button" onClick={onClose} aria-label="Tutup" className="inline-flex h-9 w-9 items-center justify-center rounded-lg text-muted hover:bg-paper"><X size={20} aria-hidden /></button>
            </div>
            <div className="p-5">{open && children}</div>
        </dialog>
    );
}
