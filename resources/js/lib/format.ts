/** '4.5000' -> '4,50'. Server rounds; this only trims zeros and uses the Indonesian decimal comma. */
export function gram(value: string | null, minDecimals = 2): string {
    if (value === null || value === undefined) return '';
    const [int, dec = ''] = value.split('.');
    let d = dec.replace(/0+$/, '');
    while (d.length < minDecimals) d += '0';
    const grouped = int.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return d ? `${grouped},${d}` : grouped;
}

/** For text inputs: '4.5000' -> '4,5'. */
export function inputNumber(value: string | null): string {
    return value === null ? '' : gram(value, 0);
}

export function rupiah(value: string | null): string {
    return value === null ? '' : `Rp ${gram(value, 0)}`;
}

/** '0.025000' -> '2,5' (a rate as a percentage, without floating point). */
export function percent(rate: string): string {
    const [, dec = ''] = rate.split('.');
    const digits = dec.replace(/0+$/, '').padEnd(2, '0');
    const whole = parseInt(digits.slice(0, 2), 10);
    const frac = digits.slice(2);
    return frac ? `${whole},${frac}` : String(whole);
}

export const KIND_LABEL ={ bar: 'Batangan', jewelry: 'Perhiasan' } as const;

export const PURITY_UNIT = { permil: '‰', percent: '%', karat: 'karat' } as const;

export function purityLabel(source: string | null, input: string | null): string {
    if (!source || input === null) return 'Kadar belum diisi';
    return `${inputNumber(input)} ${PURITY_UNIT[source as keyof typeof PURITY_UNIT]}`;
}

export const MISSING_LABEL: Record<string, string> = {
    unit_weight_g: 'berat per keping',
    quantity: 'jumlah keping',
    gross_weight_g: 'berat kotor',
    stone_weight_g: 'berat batu',
    purity_fraction: 'kadar',
};

export function missingText(missing: string[]): string {
    return missing.map((m) => MISSING_LABEL[m] ?? m).join(', ');
}

export const DATE_CERTAINTY: Record<string, string> = { exact: 'Pasti', approximate: 'Perkiraan', unknown: 'Tidak tahu' };

export function dateId(value: string | null): string {
    if (!value) return '';
    const [y, m, d] = value.split('-');
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    return `${Number(d)} ${months[Number(m) - 1]} ${y}`;
}

/** Rupiah exactly as the server computed it: whole rupiah when the cents are 00, otherwise both decimals ("909,20", not "909,2"). */
export function rupiahCents(value: string | null): string {
    if (value === null) return '';
    return `Rp ${value.endsWith('.00') ? gram(value.slice(0, -3), 0) : gram(value, 2)}`;
}
