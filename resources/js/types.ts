export type Toast = { type: 'success' | 'error' | 'info'; message: string };

export type AuthUser = { id: string; name: string; email: string; hide_amounts: boolean };

export type SharedProps = {
    auth: { user: AuthUser | null };
    toast: Toast | null;
    new_location_id: string | null;
    [key: string]: unknown;
};

export type Loc = { id: string; name: string; kind: string; items_count?: number };

export type ItemUnit = { id: string; serial_number: string | null; quantity: number };

/** What ItemPresenter returns. Decimals are strings; null means unknown, never zero. */
export type Item = {
    id: string;
    kind: 'bar' | 'jewelry';
    name: string;
    brand: string | null;
    jewelry_type: string | null;
    package_condition: string | null;
    ownership: 'own' | 'held_for_others';
    held_for: string | null;
    unit_weight_g: string | null;
    gross_weight_g: string | null;
    stone_state: 'none' | 'known' | 'unknown' | null;
    stone_weight_g: string | null;
    net_weight_g: string | null;
    purity_source: 'permil' | 'percent' | 'karat' | null;
    purity_input: string | null;
    purpose: string | null;
    acquisition_method: string;
    acquisition_cost: string | null;
    owned_since: string | null;
    owned_since_certainty: string;
    owned_since_note: string | null;
    haul_start: string | null;
    haul_start_certainty: string | null;
    haul_start_note: string | null;
    notes: string | null;
    location_id: string | null;
    location: { id: string; name: string } | null;
    created_at: string;
    units: ItemUnit[];
    pieces: number;
    purity_estimate: boolean;
    net_g: string | null;
    pure_gold_g: string | null;
    status: 'complete' | 'needs_data';
    missing: string[];
};
