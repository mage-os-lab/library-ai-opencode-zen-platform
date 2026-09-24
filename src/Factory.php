<?php

declare(strict_types=1);

namespace MageOS\AiOpenCodeZenPlatform;

use Symfony\AI\Platform\Bridge\Generic\Factory as GenericFactory;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\ModelRouter\CatalogBasedModelRouter;
use Symfony\AI\Platform\ModelRouterInterface;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\ProviderInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Symfony AI platform bridge for the OpenCode Zen gateway.
 *
 * Upstream ships one package per provider, but none for OpenCode, which is why this exists at all.
 * Zen speaks the OpenAI Chat Completions body on `/v1/chat/completions`, so the whole bridge is the
 * generic OpenAI-compatible client pointed at Zen's host: there is no Zen-specific request or
 * response shape to translate.
 *
 * The parameter order deliberately mirrors `Symfony\AI\Platform\Bridge\OpenRouter\Factory` rather
 * than `Symfony\AI\Platform\Bridge\Generic\Factory`, which this delegates to. The generic factory
 * takes its base URL first and the credential second; every hosted bridge takes the credential
 * first. Callers that dispatch over a registry of bridge factories — `MageOS_AiBase` among them —
 * pass the API key positionally, so a base-URL-first signature would silently send the key as the
 * host.
 *
 * Zen routes only part of its catalogue through Chat Completions; `gpt-*`, `claude-*`, `gemini-*`,
 * `grok-*`, `qwen*` and `jev-*` are served from `/v1/responses`, `/v1/messages`,
 * `/v1/models/<id>` and `/v1/systemone` respectively and cannot be reached through this bridge.
 * See the README.
 */
class Factory
{
    /**
     * Host and path prefix of the hosted Zen gateway.
     *
     * Overridable per call because the same wire format is what a proxy in front of Zen, or a
     * gateway an organisation runs itself, exposes; the endpoint is not part of the protocol.
     */
    public const DEFAULT_BASE_URL = 'https://opencode.ai/zen';

    /**
     * Path appended to the base URL for chat completions.
     *
     * Named rather than inlined because it is the one thing that decides which slice of Zen's
     * catalogue this bridge can reach, and a reader looking for that should find it here.
     */
    public const COMPLETIONS_PATH = '/v1/chat/completions';

    /**
     * Provider name reported to the platform, and the key a model router resolves against.
     */
    public const PROVIDER_NAME = 'opencode-zen';

    /**
     * Build the Zen provider.
     *
     * Embeddings are switched off because Zen exposes no `/v1/embeddings` endpoint; wiring the
     * generic embeddings client anyway would accept an embeddings model and then fail with a 404
     * from a path that does not exist, rather than with "no client supports this model".
     *
     * @param string $apiKey Zen API key from https://opencode.ai/auth
     * @param HttpClientInterface|null $httpClient
     * @param ModelCatalogInterface $modelCatalog
     * @param Contract|null $contract
     * @param EventDispatcherInterface|null $eventDispatcher
     * @param non-empty-string $name
     * @param string $baseUrl
     * @return ProviderInterface
     */
    public static function createProvider(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = self::PROVIDER_NAME,
        string $baseUrl = self::DEFAULT_BASE_URL,
    ): ProviderInterface {
        return GenericFactory::createProvider(
            baseUrl: $baseUrl,
            apiKey: $apiKey,
            httpClient: $httpClient,
            modelCatalog: $modelCatalog,
            contract: $contract,
            eventDispatcher: $eventDispatcher,
            supportsEmbeddings: false,
            completionsPath: self::COMPLETIONS_PATH,
            name: $name,
        );
    }

    /**
     * Build a platform holding nothing but the Zen provider.
     *
     * @param string $apiKey Zen API key from https://opencode.ai/auth
     * @param HttpClientInterface|null $httpClient
     * @param ModelCatalogInterface $modelCatalog
     * @param Contract|null $contract
     * @param EventDispatcherInterface|null $eventDispatcher
     * @param non-empty-string $name
     * @param ModelRouterInterface|null $modelRouter
     * @param string $baseUrl
     * @return Platform
     */
    public static function createPlatform(
        #[\SensitiveParameter] string $apiKey,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = self::PROVIDER_NAME,
        ?ModelRouterInterface $modelRouter = null,
        string $baseUrl = self::DEFAULT_BASE_URL,
    ): Platform {
        return new Platform(
            [
                self::createProvider(
                    $apiKey,
                    $httpClient,
                    $modelCatalog,
                    $contract,
                    $eventDispatcher,
                    $name,
                    $baseUrl,
                ),
            ],
            $modelRouter ?? new CatalogBasedModelRouter(),
            $eventDispatcher,
        );
    }
}
