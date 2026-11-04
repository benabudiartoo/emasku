export type Toast = { type: 'success' | 'error' | 'info'; message: string };

export type AuthUser = { id: string; name: string; email: string; hide_amounts: boolean; is_admin: boolean };

export type SharedProps = {
    auth: { user: AuthUser | null };
    toast: Toast | null;
    [key: string]: unknown;
};

export type ZakatReference = { name: string; url: string; accessed: string; note?: string };

export type ZakatMadhhab = {
    key: string;
    label: string;
    version: string;
    nisab_g: string;
    worn_jewelry_zakatable: boolean;
    references: ZakatReference[];
};

export type ZakatOutcome = 'due' | 'below_nisab' | 'haul_incomplete';

/** One calculation, fresh (preview) or stored (period). Decimals are strings; null means not known. */
export type ZakatView = {
    id: string | null;
    madhhab: string;
    madhhab_label: string;
    rule_version: string;
    rule_outdated: boolean;
    valuation_date: string;
    status: 'complete' | 'needs_review';
    outcome: ZakatOutcome | null;
    nisab_g: string;
    rate: string;
    eligible_g: string | null;
    shortfall_g: string | null;
    haul_start: string | null;
    haul_end: string | null;
    haul_start_hijri: string | null;
    haul_end_hijri: string | null;
    price_per_gram: string | null;
    discounted_price: string | null;
    zakat_g: string | null;
    zakat_rupiah: string | null;
    contributions: { item_id: string; name: string; kind: string; pure_gold_g: string | null; included: boolean; reason: string | null; linkable?: boolean }[];
    reasons: { code: string; message: string; item_id?: string }[];
    steps: { rule: string; text: string }[];
    references: ZakatReference[];
};

/** Whether the "Ambil harga terbaru" button is offered (a key is set), and the link the provider asks us to show. */
export type PriceFeed = { available: boolean; attribution: { name: string; url: string } };

/** What GET /zakat/harga-emas returns on success. */
export type PriceQuote = {
    price_per_gram: string;
    currency: 'IDR';
    source: string;
    fx_source: string;
    fx_date: string | null;
    fetched_at: string;
    note: string;
};

export type ZakatSummary = {
    id: string;
    valuation_date: string;
    madhhab_label: string;
    status: 'complete' | 'needs_review';
    outcome: ZakatOutcome | null;
    eligible_g: string | null;
    zakat_g: string | null;
    zakat_rupiah: string | null;
};


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
    /** The purity written in karat, whatever unit it was saved in. */
    purity_karat: string | null;
    purpose: string | null;
    acquisition_cost: string | null;
    purchase_store: string | null;
    resale_discount_percent: string | null;
    price_per_gram: string | null;
    discounted_price: string | null;
    owned_since: string | null;
    owned_since_certainty: string;
    haul_start: string | null;
    haul_start_certainty: string | null;
    haul_start_note: string | null;
    notes: string | null;
    created_at: string;
    units: ItemUnit[];
    pieces: number;
    purity_estimate: boolean;
    net_g: string | null;
    pure_gold_g: string | null;
    status: 'complete' | 'needs_data';
    missing: string[];
};

/** Gram figures for the summary charts (computed on the server from the owner's complete own items). */
export type ChartRow = { label: string; pure_gold_g: string };

export type SummaryChartsData = {
    by_kind: ChartRow[];
    growth: {
        points: { date: string; cumulative_g: string }[];
        undated_count: number;
        approximate_count: number;
    };
};

/** What GET /ringkasan/nilai returns on success: value in rupiah and the comparison with the purchase price. */
export type PortfolioValueData = {
    price: PriceQuote;
    total_value: string;
    comparison: {
        cost: string;
        value: string;
        difference: string;
        difference_percent: string | null;
        items_with_cost: number;
        items_without_cost: number;
    } | null;
    resale: ResaleSummary;
};

/** The light admin page: counts only, never an owner's items, weights, values, or zakat results. */
export type AdminTotals = { users: number; active_30d: number; new_30d: number; items: number; zakat_periods: number };

export type AdminUser = {
    id: string;
    name: string;
    email: string;
    is_admin: boolean;
    created_at: string | null;
    last_login_at: string | null;
    items_count: number;
    zakat_periods_count: number;
};

export type AdminUsers = {
    data: AdminUser[];
    total: number;
    from: number | null;
    to: number | null;
    current_page: number;
    last_page: number;
    prev_url: string | null;
    next_url: string | null;
};

/** The resale estimate of one item (GET /koleksi/{item}/perkiraan-jual). `market` is null when there is no market price to compare with. */
export type ResaleBreakdown = {
    estimable: boolean;
    missing: ('acquisition_cost' | 'resale_discount_percent' | 'net_weight')[];
    discounted: string | null;
    market: string | null;
    estimate: string | null;
    basis: 'market' | 'discounted' | 'discounted_only' | null;
    market_reason: 'price_unavailable' | 'purity_unknown' | null;
};

export type ResaleEstimateData = { estimate: ResaleBreakdown; price: PriceQuote | null; price_message: string | null };

/** The "Perkiraan hasil jual" totals from GET /ringkasan/nilai. */
export type ResaleSummary = {
    estimated_total: string;
    cost: string;
    difference: string;
    difference_percent: string | null;
    items_estimated: number;
    items_using_market: number;
    items_not_estimable: number;
};

/** One row read from an uploaded workbook, as shown in the import preview. */
export type ImportRow = {
    row: number;
    name: string;
    store: string | null;
    jewelry_type: string | null;
    owned_since: string | null;
    purity_source: string | null;
    purity_input: string | null;
    purity_text: string;
    net_weight_g: string | null;
    acquisition_cost: string | null;
    resale_discount_percent: string | null;
    status: 'ok' | 'check' | 'duplicate' | 'error';
    notes: string[];
    selected: boolean;
};
