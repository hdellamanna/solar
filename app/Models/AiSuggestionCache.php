<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSuggestionCache extends Model
{
    protected $table = 'ai_suggestion_cache';
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'description_hash', 'suggested_category_id',
        'provider', 'confidence', 'created_at', 'expires_at',
    ];

    protected $casts = [
        'confidence' => 'float',
        'created_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function category(): BelongsTo { return $this->belongsTo(Category::class, 'suggested_category_id'); }
}
