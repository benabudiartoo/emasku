import type { ReactNode } from 'react';

export default function Field({ id, label, error, hint, children }: { id: string; label: string; error?: string; hint?: string; children: ReactNode }) {
    return (
        <div>
            <label htmlFor={id} className="block text-sm font-medium">{label}</label>
            {children}
            {hint && !error && <p className="mt-1 text-xs text-muted">{hint}</p>}
            {error && <p role="alert" className="mt-1 text-sm text-red-800">{error}</p>}
        </div>
    );
}
