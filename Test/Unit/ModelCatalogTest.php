<?php

declare(strict_types=1);

namespace MageOS\AiOpenCodeZenPlatform\Test\Unit;

use MageOS\AiOpenCodeZenPlatform\ModelCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Exception\InvalidArgumentException;

final class ModelCatalogTest extends TestCase
{
    /**
     * The whole point of this catalogue: a model id it has never heard of resolves anyway, so a
     * model Zen shipped after this package was released is reachable without a new release.
     *
     * @param string $modelName
     */
    #[DataProvider('model_names')]
    public function test_it_resolves_any_model_name(string $modelName): void
    {
        $model = (new ModelCatalog())->getModel($modelName);

        self::assertInstanceOf(CompletionsModel::class, $model);
        self::assertSame($modelName, $model->getName());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function model_names(): array
    {
        return [
            'a documented chat-completions model' => ['kimi-k3'],
            'a version with a dot'                => ['glm-5.3-flash'],
            'a free-tier stealth model'           => ['big-pickle'],
            'one nobody has released yet'         => ['not-invented-yet-9000'],
        ];
    }

    /**
     * Query options travel with the name, which is how a caller pins a per-model default without
     * this package knowing the option exists.
     */
    public function test_it_parses_options_off_the_model_name(): void
    {
        $model = (new ModelCatalog())->getModel('kimi-k3?temperature=0.4');

        self::assertSame('kimi-k3', $model->getName());
        self::assertSame(['temperature' => 0.4], $model->getOptions());
    }

    /**
     * An empty model name is the one thing still refused locally: there is no request to make, and
     * the gateway would answer for a path with nothing on it.
     */
    public function test_it_refuses_an_empty_model_name(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ModelCatalog())->getModel('   ');
    }

    /**
     * Reported empty on purpose. A consumer inspecting the catalogue to decide what to offer, or to
     * merge an administrator's own choice into, gets the honest answer: this one constrains nothing,
     * so there is nothing to merge into.
     */
    public function test_it_enumerates_nothing(): void
    {
        self::assertSame([], (new ModelCatalog())->getModels());
    }
}
