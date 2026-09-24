# mage-os/library-ai-opencode-zen-platform

A [Symfony AI](https://github.com/symfony/ai) platform bridge for the
[OpenCode Zen](https://opencode.ai/docs/zen/) gateway.

Symfony ships one bridge package per provider — `symfony/ai-open-router-platform`,
`symfony/ai-deep-seek-platform` and so on — but none for OpenCode. This package fills that gap so
that OpenCode Zen can be used anywhere a `Symfony\AI\Platform\PlatformInterface` is accepted,
including as the client behind the `opencode-zen` provider of
[`mage-os/module-ai-base`](https://github.com/mage-os-lab/module-ai-base).

This is a plain PHP library. It contains no Magento code and needs no `setup:upgrade`.

## Installation

```bash
composer require mage-os/library-ai-opencode-zen-platform
```

## Usage

```php
use MageOS\AiOpenCodeZenPlatform\Factory;

$platform = Factory::createPlatform($apiKey);

$result = $platform->invoke('kimi-k2.6', 'Why is the sky blue?');
echo $result->asText();
```

Point it somewhere else — a proxy in front of Zen, or a gateway run in-house that speaks the same
wire format — with the `baseUrl` argument:

```php
$platform = Factory::createPlatform($apiKey, baseUrl: 'https://ai.example.com/zen');
```

Get your API key from [opencode.ai/auth](https://opencode.ai/auth).

## Scope: Chat Completions only

Zen is a gateway, and it serves different model families from different endpoints. This bridge
targets **`/v1/chat/completions`** only, which covers:

| Family | Example ids |
|---|---|
| DeepSeek | `deepseek-v4-pro`, `deepseek-v4-flash`, `deepseek-v4.1-flash` |
| MiniMax | `minimax-m3`, `minimax-m2.7` |
| GLM | `glm-5.3`, `glm-5.3-flash` |
| Kimi | `kimi-k3`, `kimi-k2.7-code` |
| Free tier | `big-pickle`, `space-bunny-free`, `nemotron-3-ultra-free`, … |

Model ids Zen serves from **other** endpoints cannot be reached through this bridge and will fail
against the gateway:

| Family | Zen endpoint |
|---|---|
| `gpt-*`, `grok-*`, `muse-*` | `/v1/responses` |
| `claude-*`, `qwen*` | `/v1/messages` |
| `gemini-*` | `/v1/models/<id>` |
| `jev-*` | `/v1/systemone` |

For those, use `symfony/ai-open-responses-platform`, `symfony/ai-anthropic-platform` or
`symfony/ai-gemini-platform` pointed at the matching Zen endpoint instead.

Embeddings are not wired: Zen exposes no `/v1/embeddings` endpoint.

## Model catalogue

`MageOS\AiOpenCodeZenPlatform\ModelCatalog` accepts **any** model id and routes it to the
chat-completions client. It deliberately enumerates nothing.

Every other bridge ships a static list of the models its provider served on release day. That works
for a provider with a handful of long-lived names; Zen gains and deprecates models monthly and
publishes its catalogue as an endpoint (`https://opencode.ai/zen/v1/models`, which is readable
without a key). A frozen copy would reject a model an administrator can see in the gateway's own
listing, so this bridge lets the gateway answer for its own catalogue.

The consequence is that an unreachable model id — `gpt-5.5`, say — is accepted locally and fails at
Zen with Zen's own message, rather than being rejected here with a local one.

## Versioning

`symfony/ai-platform` is experimental and makes no backward-compatibility promise. This package is
verified against **v0.13.0** and constrains itself to `^0.13`; re-verify on upgrade.
