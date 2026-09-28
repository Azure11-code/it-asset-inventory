<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The chat endpoint's failure paths.
 *
 * The important property here is that nothing from the upstream response —
 * least of all the API key — reaches the browser. A raw error body used to be
 * forwarded straight into the chat window and saved to localStorage with it.
 */
class AiChatTest extends TestCase
{
    use DatabaseTransactions;

    private const FAKE_KEY = 'AIzaTESTKEYzzz1234567890';

    private ?User $user = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Retries must not actually sleep during tests.
        config(['services.gemini.retry_sleep_cap' => 0]);
    }

    private function user(): User
    {
        return $this->user ??= User::create([
            'name'     => 'AI Testzzz',
            'username' => 'aitestzzz',
            'email'    => 'aitestzzz@example.test',
            'password' => bcrypt('secret'),
            'is_admin' => true,
        ]);
    }

    private function ask(): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user())
            ->postJson('/ai/chat', ['message' => 'how many laptops?']);
    }

    private function fakeGemini(int $status, array $body = []): void
    {
        config(['services.gemini.api_key' => self::FAKE_KEY]);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($body, $status)]);
    }

    public function test_a_rejected_api_key_produces_an_actionable_message_not_the_upstream_body(): void
    {
        $this->fakeGemini(401, ['error' => [
            'code'    => 401,
            'message' => 'Request had invalid authentication credentials. Expected OAuth 2 access token…',
            'status'  => 'UNAUTHENTICATED',
        ]]);

        $response = $this->ask();

        $response->assertStatus(502);
        $response->assertJson(['ok' => false]);

        $error = $response->json('error');
        $this->assertStringContainsString('GEMINI_API_KEY', $error);
        $this->assertStringContainsString('ai:check', $error);

        // None of Google's raw error text is passed through.
        $this->assertStringNotContainsString('OAuth 2 access token', $error);
        $this->assertStringNotContainsString('UNAUTHENTICATED', $error);
    }

    public function test_the_api_key_never_reaches_the_browser(): void
    {
        $this->fakeGemini(401, ['error' => ['message' => 'bad key ' . self::FAKE_KEY]]);

        $response = $this->ask();

        $this->assertStringNotContainsString(self::FAKE_KEY, $response->getContent());
    }

    public function test_the_api_key_is_sent_as_a_header_and_not_in_the_url(): void
    {
        $this->fakeGemini(200, ['candidates' => [
            ['content' => ['parts' => [['text' => 'Four laptops.']]]],
        ]]);

        $this->ask()->assertOk()->assertJson(['ok' => true, 'text' => 'Four laptops.']);

        Http::assertSent(function ($request) {
            // A key in the query string leaks into proxy logs and exception messages.
            $this->assertStringNotContainsString(self::FAKE_KEY, $request->url());
            $this->assertSame(self::FAKE_KEY, $request->header('x-goog-api-key')[0] ?? null);

            return true;
        });
    }

    public function test_an_unavailable_model_is_explained(): void
    {
        config(['services.gemini.model' => 'gemini-not-a-real-model']);
        $this->fakeGemini(404, ['error' => ['message' => 'models/gemini-not-a-real-model is not found']]);

        $error = $this->ask()->assertStatus(502)->json('error');

        $this->assertStringContainsString('gemini-not-a-real-model', $error);
        $this->assertStringContainsString('GEMINI_MODEL', $error);
    }

    public function test_a_missing_api_key_is_reported_as_configuration_not_a_crash(): void
    {
        config(['services.gemini.api_key' => '']);

        $error = $this->ask()->assertStatus(502)->json('error');

        $this->assertStringContainsString('GEMINI_API_KEY', $error);
    }

    public function test_a_rate_limit_still_reports_the_retry_delay(): void
    {
        $this->fakeGemini(429, ['error' => [
            'message' => 'quota exceeded',
            'details' => [[
                '@type'      => 'type.googleapis.com/google.rpc.RetryInfo',
                'retryDelay' => '14s',
            ]],
        ]]);

        $response = $this->ask();

        $response->assertStatus(429);
        $response->assertJson(['ok' => false, 'rate_limit' => true, 'retry_after' => 14]);
    }

    public function test_a_dropped_connection_is_retried_before_giving_up(): void
    {
        config(['services.gemini.api_key' => self::FAKE_KEY]);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->pushFailedConnection()
            ->push(['candidates' => [['content' => ['parts' => [['text' => 'Recovered.']]]]]], 200)]);

        $this->ask()->assertOk()->assertJson(['ok' => true, 'text' => 'Recovered.']);
    }

    public function test_a_connection_that_never_recovers_is_reported_plainly(): void
    {
        config(['services.gemini.api_key' => self::FAKE_KEY]);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::failedConnection()]);

        $error = $this->ask()->assertStatus(502)->json('error');

        $this->assertStringContainsString('Could not reach the AI service', $error);
    }

    public function test_it_validates_the_message(): void
    {
        $this->actingAs($this->user())
            ->postJson('/ai/chat', ['message' => ''])
            ->assertStatus(422);

        $this->actingAs($this->user())
            ->postJson('/ai/chat', ['message' => 'hi', 'history' => [['role' => 'system', 'text' => 'x']]])
            ->assertStatus(422);
    }

    public function test_it_requires_authentication(): void
    {
        $this->postJson('/ai/chat', ['message' => 'hi'])->assertStatus(401);
    }
}
