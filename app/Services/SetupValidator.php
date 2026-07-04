<?php

namespace App\Services;

use App\Models\AppMeta;

/**
 * Collects the current state of every env var the SetupController needs to
 * report. Lives as a service so it can be unit-tested without booting HTTP.
 *
 * The wizard at /setup reads the env-var list from this validator and
 * renders each entry with: label, description, current value (masked for
 * secrets), is_set boolean, and required boolean.
 *
 * Adding a new env var to the wizard = adding one entry to the
 * REQUIRED_VARS or OPTIONAL_VARS array below.
 */
class SetupValidator
{
    /**
     * Env vars the wizard actively monitors and prompts the operator about.
     * Each entry is: [label, description, is_secret].
     */
    public const REQUIRED_VARS = [
        ['key' => 'APP_KEY',          'label' => 'APP_KEY',          'description' => 'Chave de criptografia do Laravel (32 bytes base64). Gera automaticamente se vazia.', 'secret' => true],
        ['key' => 'APP_URL',          'label' => 'APP_URL',          'description' => 'URL pública do app (sem barra final). Render injeta do host do serviço.', 'secret' => false],
        ['key' => 'DB_CONNECTION',    'label' => 'DB_CONNECTION',    'description' => 'Driver do banco (sqlite, pgsql, mysql). Padrão: sqlite local; pgsql no Render.', 'secret' => false],
        ['key' => 'MAIL_MAILER',      'label' => 'MAIL_MAILER',      'description' => 'Driver de email. "log" em dev (não envia), "resend" em produção.', 'secret' => false],
        ['key' => 'MAIL_FROM_ADDRESS','label' => 'MAIL_FROM_ADDRESS','description' => 'Endereço do remetente (ex: noreply@solar.app). Deve ser um domínio verificado no Resend.', 'secret' => false],
    ];

    public const OPTIONAL_VARS = [
        ['key' => 'APP_LOCALE',           'label' => 'APP_LOCALE',           'description' => 'Locale padrão. Valores: pt-BR, es, en.', 'secret' => false],
        ['key' => 'SESSION_DRIVER',       'label' => 'SESSION_DRIVER',       'description' => 'Onde guardar sessões. "database" em produção, "array" em testes.', 'secret' => false],
        ['key' => 'CACHE_STORE',          'label' => 'CACHE_STORE',          'description' => 'Onde guardar cache. "database" em produção, "array" em testes.', 'secret' => false],
        ['key' => 'QUEUE_CONNECTION',     'label' => 'QUEUE_CONNECTION',     'description' => 'Driver de filas. "sync" bloqueia (dev), "database" processa em background (prod).', 'secret' => false],
        ['key' => 'RESEND_API_KEY',       'label' => 'RESEND_API_KEY',       'description' => 'Chave do Resend (https://resend.com/api-keys). Necessária para emails reais.', 'secret' => true],
        ['key' => 'SENTRY_LARAVEL_DSN',   'label' => 'SENTRY_LARAVEL_DSN',   'description' => 'DSN do Sentry para tracking de erros (opcional).', 'secret' => true],
        // OAuth (placeholders for FASE 8)
        ['key' => 'GOOGLE_CLIENT_ID',     'label' => 'GOOGLE_CLIENT_ID',     'description' => 'Google OAuth client ID (FASE 8 — Social Login).', 'secret' => true],
        ['key' => 'GOOGLE_CLIENT_SECRET', 'label' => 'GOOGLE_CLIENT_SECRET', 'description' => 'Google OAuth client secret (FASE 8).', 'secret' => true],
        ['key' => 'APPLE_CLIENT_ID',      'label' => 'APPLE_CLIENT_ID',      'description' => 'Apple Sign In client ID (FASE 8).', 'secret' => true],
        ['key' => 'APPLE_CLIENT_SECRET',  'label' => 'APPLE_CLIENT_SECRET',  'description' => 'Apple Sign In client secret (FASE 8).', 'secret' => true],
        ['key' => 'MICROSOFT_CLIENT_ID',  'label' => 'MICROSOFT_CLIENT_ID',  'description' => 'Microsoft OAuth client ID (FASE 8).', 'secret' => true],
        ['key' => 'MICROSOFT_CLIENT_SECRET','label' => 'MICROSOFT_CLIENT_SECRET','description' => 'Microsoft OAuth client secret (FASE 8).', 'secret' => true],
    ];

    /**
     * Returns a list of every known env var with its current state.
     * Used by the wizard to render the form.
     *
     * @return array<int, array{key: string, label: string, description: string, secret: bool, required: bool, current_value: ?string, is_set: bool}>
     */
    public function collect(): array
    {
        $out = [];

        foreach (self::REQUIRED_VARS as $entry) {
            $out[] = $this->buildEntry($entry, required: true);
        }
        foreach (self::OPTIONAL_VARS as $entry) {
            $out[] = $this->buildEntry($entry, required: false);
        }

        return $out;
    }

    /**
     * Returns true when every REQUIRED_VAR is set with a non-empty value.
     * OPTIONAL_VARS are not blocking — they show "not configured" but the
     * wizard can finish without them.
     */
    public function requiredAreSet(): bool
    {
        foreach (self::REQUIRED_VARS as $entry) {
            if (! $this->isSet($entry['key'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns true if setup has been completed before (either by the
     * entrypoint script, the wizard, or a manual mark).
     */
    public function setupCompleted(): bool
    {
        return AppMeta::get('setup_completed_at') !== null;
    }

    /**
     * Build one entry for the collect() output.
     *
     * @param  array{key: string, label: string, description: string, secret: bool}  $entry
     * @return array{key: string, label: string, description: string, secret: bool, required: bool, current_value: ?string, is_set: bool}
     */
    private function buildEntry(array $entry, bool $required): array
    {
        $value = $this->resolve($entry['key']);
        $isSet = $value !== null && $value !== '' && $value !== false;

        return [
            'key'           => $entry['key'],
            'label'         => $entry['label'],
            'description'   => $entry['description'],
            'secret'        => $entry['secret'],
            'required'      => $required,
            'current_value' => $isSet ? ($entry['secret'] ? $this->mask($value) : $value) : null,
            'is_set'        => $isSet,
        ];
    }

    /**
     * Resolve an env var, falling back to config() when env() returns null.
     * Some env vars (APP_KEY, APP_URL) are populated by Laravel into the
     * config at boot, so config() is the more reliable source in tests and
     * in CLI contexts.
     *
     * For keys that we explicitly map to a config() key, config() takes
     * priority over env() — even when config is null/empty. This lets
     * tests set config([... => null]) to simulate "operator unset" without
     * the live .env value leaking in via the env() fallback.
     *
     * For keys NOT in the map (default branch), env() is the only source.
     */
    private function resolve(string $key): ?string
    {
        $configKey = match ($key) {
            'APP_KEY'           => 'app.key',
            'APP_URL'           => 'app.url',
            'DB_CONNECTION'     => 'database.default',
            'MAIL_MAILER'       => 'mail.default',
            'MAIL_FROM_ADDRESS' => 'mail.from.address',
            'APP_LOCALE'        => 'app.locale',
            'SESSION_DRIVER'    => 'session.driver',
            'CACHE_STORE'       => 'cache.default',
            'QUEUE_CONNECTION'  => 'queue.default',
            'FILESYSTEM_DISK'   => 'filesystems.default',
            'RESEND_API_KEY'    => 'services.resend.key',
            // OAuth providers (FASE 8 — config/services.php keys added when implemented)
            'GOOGLE_CLIENT_ID'     => 'services.google.client_id',
            'GOOGLE_CLIENT_SECRET' => 'services.google.client_secret',
            'APPLE_CLIENT_ID'      => 'services.apple.client_id',
            'APPLE_CLIENT_SECRET'  => 'services.apple.client_secret',
            'MICROSOFT_CLIENT_ID'  => 'services.microsoft.client_id',
            'MICROSOFT_CLIENT_SECRET' => 'services.microsoft.client_secret',
            default             => null,
        };

        if ($configKey !== null) {
            // config() takes priority. Even when config is null/empty,
            // we don't fall back to env() — config is the operator's
            // explicit intent. This matches the test contract: setting
            // config([key => null]) must produce the "missing" state.
            $configValue = config($configKey);
            if ($configValue !== null && $configValue !== '' && $configValue !== false) {
                return (string) $configValue;
            }
            return null;
        }

        $value = env($key);
        if ($value !== null && $value !== '' && $value !== false) {
            return (string) $value;
        }

        return null;
    }

    /**
     * Same as resolve() but returns a boolean — for the is_set checks.
     */
    private function isSet(string $key): bool
    {
        return $this->resolve($key) !== null;
    }

    /**
     * Mask a secret value for display: keep the first 4 chars, replace the
     * rest with bullets. Lets the operator confirm "yes, my key starts
     * with re_K" without exposing the full value in the rendered HTML.
     */
    private function mask(string $value): string
    {
        if (strlen($value) <= 4) {
            return str_repeat('•', strlen($value));
        }

        return substr($value, 0, 4) . str_repeat('•', max(8, strlen($value) - 4));
    }
}