<?php

namespace App\Models;

use App\Enums\CreditTransactionType;
use Database\Factories\CreditTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'generation_id',
    'type',
    'amount',
    'balance_after',
    'currency',
    'idempotency_key',
    'description',
    'metadata',
])]
class CreditTransaction extends Model
{
    /** @use HasFactory<CreditTransactionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Generation, $this>
     */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }

    public function isCredit(): bool
    {
        return bccomp((string) $this->amount, '0', 9) === 1;
    }

    public function isDebit(): bool
    {
        return bccomp((string) $this->amount, '0', 9) === -1;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CreditTransactionType::class,
            'amount' => 'decimal:9',
            'balance_after' => 'decimal:9',
            'metadata' => 'array',
        ];
    }
}
