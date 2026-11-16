<?php

namespace App\Models;

use App\Enums\GenerationQuality;
use App\Enums\GenerationSize;
use App\Enums\GenerationStatus;
use Database\Factories\GenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'client_request_id',
    'pixelforge_id',
    'prompt',
    'size',
    'quality',
    'reference_images',
    'metadata',
    'status',
    'estimated_provider_cost',
    'estimated_customer_cost',
    'actual_provider_cost',
    'actual_customer_cost',
    'pricing_snapshot',
    'usage',
    'image_url',
    'image_url_expires_at',
    'error_code',
    'error_message',
    'submitted_at',
    'started_at',
    'completed_at',
])]
class Generation extends Model
{
    /** @use HasFactory<GenerationFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<WebhookEvent, $this>
     */
    public function webhookEvents(): HasMany
    {
        return $this->hasMany(WebhookEvent::class);
    }

    /**
     * @return HasMany<CreditTransaction, $this>
     */
    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => GenerationSize::class,
            'quality' => GenerationQuality::class,
            'status' => GenerationStatus::class,
            'reference_images' => 'array',
            'metadata' => 'array',
            'pricing_snapshot' => 'array',
            'usage' => 'array',
            'estimated_provider_cost' => 'decimal:9',
            'estimated_customer_cost' => 'decimal:9',
            'actual_provider_cost' => 'decimal:9',
            'actual_customer_cost' => 'decimal:9',
            'image_url_expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
