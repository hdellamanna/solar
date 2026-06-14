<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Simple key-value store for build metadata (version, env, etc.).
 * Used by the footer and AI agent slot to know their build context.
 */
class AppMeta extends Model
{
    protected $table = 'app_meta';

    public $timestamps = false;

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Read a value by key, returning $default if the key does not exist.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $row = (new static)->find($key);

        if ($row === null) {
            return $default;
        }

        // Try to decode as JSON first; fall back to the raw string.
        $val = $row->value;
        $decoded = json_decode($val, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $val;
    }

    /**
     * Persist a value by key. Overwrites any existing value.
     */
    public static function set(string $key, mixed $value): void
    {
        (new static)->updateOrCreate(
            ['key' => $key],
            ['value' => is_scalar($value) ? (string) $value : json_encode($value)],
        );
    }
}