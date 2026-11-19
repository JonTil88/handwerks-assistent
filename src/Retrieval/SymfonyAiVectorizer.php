<?php

declare(strict_types=1);

namespace Craft\Assistant\Retrieval;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\Component\DependencyInjection\Attribute\Target;

/**
 * Embeddings via the OpenAI-compatible interface of Ollama
 * (POST /v1/embeddings) — fully local, no API key.
 *
 * The Symfony AI Ollama bridge (1.0.0-BETA1) carries no embedding models in
 * its own catalogue, which is why the embedding side uses the generic bridge
 * against the same server.
 */
final readonly class SymfonyAiVectorizer implements Vectorizer
{
    public function __construct(
        #[Target('generic_embeddings')]
        private PlatformInterface $platform,
        private string $model = 'nomic-embed-text',
    ) {
    }

    public function vectorize(string $text): array
    {
        $result = $this->platform->invoke($this->model, $text)->getResult();

        if (!$result instanceof VectorResult) {
            throw new \RuntimeException(sprintf('Unexpected result while embedding: %s', $result::class));
        }

        $vectors = $result->getContent();
        if (0 === \count($vectors)) {
            throw new \RuntimeException('The embedding model returned no vector.');
        }

        $first = reset($vectors);
        if (!$first instanceof Vector) {
            throw new \RuntimeException('Unexpected vector format: '.get_debug_type($first));
        }

        return array_map(static fn (int|float $value): float => (float) $value, $first->getData());
    }

    public function model(): string
    {
        return $this->model;
    }

    public static function capability(): string
    {
        return Capability::EMBEDDINGS->value;
    }
}
