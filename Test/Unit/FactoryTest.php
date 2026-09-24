<?php

declare(strict_types=1);

namespace MageOS\AiOpenCodeZenPlatform\Test\Unit;

use MageOS\AiOpenCodeZenPlatform\Factory;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Platform;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * What this bridge is actually responsible for: where the request goes, how it is authenticated,
 * and the signature other code dispatches through. Response conversion is upstream's, and tested
 * there.
 */
final class FactoryTest extends TestCase
{
    /**
     * The request line, as captured from the last call made through the mock client.
     *
     * @var array{method: string, url: string, options: array<string, mixed>}|null
     */
    private ?array $captured = null;

    public function test_it_posts_to_the_zen_chat_completions_endpoint(): void
    {
        Factory::createPlatform('key-123', $this->recordingClient())->invoke('kimi-k3', self::prompt());

        self::assertNotNull($this->captured);
        self::assertSame('POST', $this->captured['method']);
        self::assertSame('https://opencode.ai/zen/v1/chat/completions', $this->captured['url']);
    }

    /**
     * The credential travels as a bearer token, which is what Zen expects and what makes the
     * generic OpenAI-compatible client usable against it without a bespoke ModelClient.
     */
    public function test_it_authenticates_with_a_bearer_token(): void
    {
        Factory::createPlatform('key-123', $this->recordingClient())->invoke('kimi-k3', self::prompt());

        self::assertNotNull($this->captured);
        self::assertContains('Authorization: Bearer key-123', $this->captured['options']['headers'] ?? []);
    }

    /**
     * The model name reaches the wire untouched. The catalogue constrains nothing, so whatever an
     * administrator typed is what Zen is asked for, including a model added after this release.
     */
    public function test_it_sends_the_model_name_it_was_given(): void
    {
        Factory::createPlatform('key-123', $this->recordingClient())->invoke('glm-5.3-flash', self::prompt());

        self::assertNotNull($this->captured);
        $body = json_decode((string) ($this->captured['options']['body'] ?? ''), true);
        self::assertIsArray($body);
        self::assertSame('glm-5.3-flash', $body['model'] ?? null);
    }

    /**
     * A proxy in front of Zen, or a gateway an organisation runs itself, speaks the same wire
     * format; the endpoint is configuration, not protocol.
     */
    public function test_it_honours_a_base_url_override(): void
    {
        Factory::createPlatform('key-123', $this->recordingClient(), baseUrl: 'https://ai.example.com/zen/')
            ->invoke('kimi-k3', self::prompt());

        self::assertNotNull($this->captured);
        self::assertSame('https://ai.example.com/zen/v1/chat/completions', $this->captured['url']);
    }

    public function test_it_builds_a_platform(): void
    {
        self::assertInstanceOf(Platform::class, Factory::createPlatform('key-123', new MockHttpClient()));
    }

    public function test_the_provider_reports_the_opencode_name(): void
    {
        self::assertSame(
            'opencode-zen',
            Factory::createProvider('key-123', new MockHttpClient())->getName(),
        );
    }

    /**
     * The contract callers dispatch through.
     *
     * `MageOS_AiBase` calls `createPlatform()` over a registry of bridge factory class names,
     * passing the credential positionally and everything else by name. The generic factory this
     * delegates to takes its base URL first instead, so a refactor that forwarded that order
     * would send the API key as the host and authenticate with nothing — and would do it at
     * runtime, against the provider, with an error that names neither.
     */
    public function test_it_takes_the_api_key_first_and_names_its_base_url_parameter(): void
    {
        $parameters = (new \ReflectionMethod(Factory::class, 'createPlatform'))->getParameters();

        self::assertSame('apiKey', $parameters[0]->getName());

        $names = array_map(static fn (\ReflectionParameter $p): string => $p->getName(), $parameters);
        self::assertContains('baseUrl', $names);
        self::assertContains('modelCatalog', $names);
    }

    /**
     * A model id that happens to contain "embed" is still a completion.
     *
     * The generic bridge's own fallback catalogue reads that substring as "this is an embeddings
     * model" and routes accordingly. This bridge wires no embeddings client, because Zen exposes no
     * `/v1/embeddings` endpoint, so inheriting that heuristic would turn one unlucky model name
     * into "no client supports this model" instead of a request the gateway can answer for.
     */
    public function test_a_model_name_containing_embed_still_goes_to_chat_completions(): void
    {
        Factory::createPlatform('key-123', $this->recordingClient())->invoke('glm-5.3-embedder', self::prompt());

        self::assertNotNull($this->captured);
        self::assertSame('https://opencode.ai/zen/v1/chat/completions', $this->captured['url']);
    }

    /**
     * The smallest input the default contract turns into a chat-completions body.
     *
     * A bare string is not one: the contract passes it through untouched and the generic client
     * refuses a non-array payload, so every assertion below would fail before reaching the wire.
     */
    private static function prompt(): MessageBag
    {
        return new MessageBag(Message::ofUser('Hello'));
    }

    /**
     * A mock client that records the request it was handed and answers with a minimal, valid
     * chat-completions body so nothing downstream fails for the wrong reason.
     */
    private function recordingClient(): MockHttpClient
    {
        return new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->captured = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse(
                (string) json_encode([
                    'choices' => [['message' => ['role' => 'assistant', 'content' => 'Hi']]],
                ]),
                ['response_headers' => ['content-type' => 'application/json']],
            );
        });
    }
}
