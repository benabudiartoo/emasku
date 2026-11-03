import { useState } from 'react';
import { Eye, EyeSlash } from '@phosphor-icons/react';

type Props = {
    id: string;
    value: string;
    onChange: (v: string) => void;
    autoComplete: 'current-password' | 'new-password';
    describedBy?: string;
};

export default function PasswordInput({ id, value, onChange, autoComplete, describedBy }: Props) {
    const [show, setShow] = useState(false);

    return (
        <div className="relative mt-1.5">
            <input
                id={id}
                name="password"
                type={show ? 'text' : 'password'}
                autoComplete={autoComplete}
                required
                minLength={8}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                aria-describedby={describedBy}
                className="h-11 w-full rounded-xl border border-line bg-white pl-3 pr-12 text-base focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
            />
            <button
                type="button"
                aria-pressed={show}
                aria-label={show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
                onClick={() => setShow(!show)}
                className="absolute right-1 top-1 grid h-9 w-9 place-items-center rounded-lg text-xl text-muted hover:bg-paper focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand"
            >
                {show ? <EyeSlash aria-hidden /> : <Eye aria-hidden />}
            </button>
        </div>
    );
}
