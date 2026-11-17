export type GenerationStatus = 'queued' | 'processing' | 'succeeded' | 'failed';

export type GenerationSize = '1024x1024' | '1536x1024' | '1024x1536';

export type GenerationQuality = 'low' | 'medium' | 'high';

export interface Generation {
    id: number;
    status: GenerationStatus;
    prompt: string;
    size: GenerationSize;
    quality: GenerationQuality;
    reference_images: string[];
    estimated_provider_cost: string | null;
    estimated_customer_cost: string | null;
    actual_provider_cost: string | null;
    actual_customer_cost: string | null;
    image_url: string | null;
    image_url_expires_at: string | null;
    error_code: string | null;
    error_message: string | null;
    submitted_at: string | null;
    started_at: string | null;
    completed_at: string | null;
    created_at: string | null;
}
