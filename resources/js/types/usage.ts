export interface UsageTotals {
    generations: number;
    input_text_tokens: number;
    input_image_tokens: number;
    output_tokens: number;
    reasoning_tokens: number;
    cost_usd: string;
    customer_cost_usd?: string;
}

export interface UsageDayRow {
    date: string;
    provider_generations: number;
    local_generations: number;
    provider_cost_usd: string;
    local_cost_usd: string;
    difference_usd: string;
    matched: boolean;
}

export interface UsageReport {
    start_date: string;
    end_date: string;
    provider: UsageTotals;
    local: UsageTotals;
    difference_usd: string;
    matched: boolean;
    days: UsageDayRow[];
}
