<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Diagnoses the AI assistant's configuration.
 *
 * The chat widget deliberately never shows the upstream error body, so this is
 * how an administrator finds out *why* it is failing: whether the key is
 * accepted, and which models that key may actually use.
 */
class AiCheck extends Command
{
    protected $signature = 'ai:check';

    protected $description = 'Check the Gemini API key and list the models it can use';

    public function handle(): int
    {
        $key   = trim((string) config('services.gemini.api_key'));
        $model = (string) config('services.gemini.model');

        $this->line('');
        $this->line('  <options=bold>AI assistant configuration</>');
        $this->line('  ─────────────────────────────────────────────');

        if ($key === '') {
            $this->line('  API key : <fg=red>not set</>');
            $this->line('  Add GEMINI_API_KEY to .env, then run: php artisan config:clear');
            $this->newLine();
            $this->line('  Get a key at https://aistudio.google.com/apikey');
            $this->newLine();

            return self::FAILURE;
        }

        $this->line('  API key : ' . $this->mask($key) . ' (' . strlen($key) . ' chars)');
        $this->line('  Model   : ' . $model);
        $this->newLine();

        // Google issues keys in more than one format ("AIza…" and "AQ.…" are both
        // valid), so the shape of the key says nothing useful. Only the API can
        // tell us whether it works — ask it.
        $this->line('  Contacting the API…');

        try {
            $resp = Http::timeout(30)
                ->withHeaders(['x-goog-api-key' => $key])
                ->get('https://generativelanguage.googleapis.com/v1beta/models');
        } catch (\Throwable $e) {
            $this->error('  ✗ Could not reach the API: ' . $e->getMessage());

            return self::FAILURE;
        }

        if (! $resp->successful()) {
            $this->error('  ✗ HTTP ' . $resp->status() . ' — ' . ($resp->json('error.message') ?? 'unknown error'));
            $this->newLine();

            if (in_array($resp->status(), [400, 401, 403], true)) {
                $this->line('  The key was rejected. Create a fresh one at https://aistudio.google.com/apikey,');
                $this->line('  put it in .env as GEMINI_API_KEY, then run: php artisan config:clear');
            }
            $this->newLine();

            return self::FAILURE;
        }

        $usable = collect($resp->json('models') ?? [])
            ->filter(fn ($m) => in_array('generateContent', $m['supportedGenerationMethods'] ?? [], true))
            ->map(fn ($m) => str_replace('models/', '', $m['name'] ?? ''))
            ->filter()
            ->values();

        $this->info('  ✓ The API key works.');
        $this->newLine();

        if ($usable->contains($model)) {
            $this->info("  ✓ The configured model \"{$model}\" is available.");
        } else {
            $this->error("  ✗ The configured model \"{$model}\" is NOT available to this key.");
            $this->line('    Set GEMINI_MODEL in .env to one of the models below, then run: php artisan config:clear');
        }

        $this->newLine();
        $this->line('  <options=bold>Models this key can use for chat:</>');
        foreach ($usable as $name) {
            $this->line('    · ' . $name);
        }
        $this->newLine();

        return $usable->contains($model) ? self::SUCCESS : self::FAILURE;
    }

    /** Shows enough of the key to identify it, never enough to use it. */
    private function mask(string $key): string
    {
        return strlen($key) <= 10
            ? str_repeat('*', strlen($key))
            : substr($key, 0, 6) . str_repeat('*', 8) . substr($key, -4);
    }
}
