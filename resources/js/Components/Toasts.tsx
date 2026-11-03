import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { CheckCircle, WarningCircle } from '@phosphor-icons/react';
import type { SharedProps, Toast } from '@/types';

export default function Toasts() {
    const { toast } = usePage<SharedProps>().props;
    const [shown, setShown] = useState<Toast | null>(null);

    useEffect(() => {
        if (!toast) return;
        setShown(toast);
        const t = setTimeout(() => setShown(null), 3200);
        return () => clearTimeout(t);
    }, [toast]);

    return (
        <div aria-live="polite" className="pointer-events-none fixed inset-x-4 bottom-20 z-[60] flex flex-col items-center md:bottom-6 md:left-auto md:right-6 md:items-end">
            {shown && (
                <div className={`pointer-events-auto flex max-w-sm items-center gap-2 rounded-xl px-3.5 py-2.5 text-sm font-medium text-white shadow-lg ${shown.type === 'error' ? 'bg-red-800' : 'bg-ink'}`}>
                    {shown.type === 'error' ? <WarningCircle size={20} aria-hidden /> : <CheckCircle size={20} aria-hidden />}
                    <span>{shown.message}</span>
                </div>
            )}
        </div>
    );
}
