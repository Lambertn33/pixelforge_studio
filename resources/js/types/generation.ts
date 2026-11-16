export type GenerationStatus = 'queued' | 'processing' | 'succeeded' | 'failed';

export interface Generation {
    id: number;
    status: GenerationStatus;
    prompt: string;
    width: number;
    height: number;
    quality: string;
    estimated_cost_nanodollars: number | null;
    actual_cost_nanodollars: number | null;
    image_url: string | null;
    created_at: string;
    updated_at: string;
}
