<?php

declare(strict_types=1);

namespace Craft\Assistant\Ai;

use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\DependencyInjection\Attribute\Target;

/**
 * A thin wrapper around the Symfony AI platform.
 *
 * All model calls run through this service — that keeps the provider
 * replaceable in exactly one place (locally via Ollama, later e.g. Anthropic).
 */
final readonly class ChatClient
{
    public function __construct(
        #[Target('ollama')]
        private PlatformInterface $platform,
        private string $model = 'qwen3:8b',
    ) {
    }

    public function ask(string $systemPrompt, string $question): string
    {
        $result = $this->platform->invoke($this->model, new MessageBag(
            Message::forSystem($systemPrompt),
            Message::ofUser($question),
        ))->getResult();

        if ($result instanceof TextResult) {
            return $result->getContent();
        }

        return (string) $result->getContent();
    }

    public function model(): string
    {
        return $this->model;
    }
}
