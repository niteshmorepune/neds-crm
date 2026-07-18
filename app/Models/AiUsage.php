<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per successful Anthropic API call (see AnthropicClient). Used for
 * cost/usage reporting. Holds no prompt or response content — customer data
 * never lands here.
 */
class AiUsage extends Model
{
    use HasFactory;

    protected $table = 'ai_usages';

    protected $fillable = [
        'feature',
        'model',
        'input_tokens',
        'output_tokens',
        'feedback',
    ];

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
        ];
    }
}
