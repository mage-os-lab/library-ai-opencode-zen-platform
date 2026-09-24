<?php

declare(strict_types=1);

namespace MageOS\AiOpenCodeZenPlatform;

use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * Accepts any Zen model id and routes it to the chat-completions client.
 *
 * Every other bridge ships a static list of the models its provider served on the day the package
 * was released, which is workable for a provider with a handful of long-lived model names. Zen is a
 * gateway: its catalogue is dozens of entries long, gains and deprecates models monthly, and is
 * published as an endpoint (`/v1/models`) rather than as a stable list. A frozen copy of it would
 * reject a model an administrator can see in the gateway's own listing, which is worse than letting
 * the gateway answer for its own catalogue.
 *
 * So there is nothing to enumerate here, and {@see getModels()} deliberately stays empty: a
 * consumer that inspects a catalogue to decide what to offer learns the honest answer, which is
 * that this one constrains nothing.
 *
 * Not extended from `Symfony\AI\Platform\Bridge\Generic\FallbackModelCatalog`, which is otherwise
 * the same idea: it routes any model whose name contains "embed" to an embeddings model, and this
 * bridge wires no embeddings client because Zen exposes no embeddings endpoint. Inheriting that
 * heuristic would turn a typo into "no client supports this model" instead of the gateway's own
 * error about a model it does not serve.
 */
class ModelCatalog extends AbstractModelCatalog
{
    /**
     * Declare the model map empty; this catalogue answers by construction rather than by lookup.
     */
    public function __construct()
    {
        $this->models = [];
    }

    /**
     * @inheritdoc
     *
     * Capabilities are reported wholesale because they only gate features a caller asks for, and
     * the gateway is the authority on what the model behind a name can actually do. Guessing
     * narrower here would block a request Zen would have served.
     */
    public function getModel(string $modelName): Model
    {
        $parsed = $this->parseModelName($modelName);
        $name = $parsed['name'];
        if ('' === trim($name)) {
            throw new InvalidArgumentException('Model name cannot be empty.');
        }

        return new CompletionsModel($name, Capability::cases(), $parsed['options']);
    }
}
