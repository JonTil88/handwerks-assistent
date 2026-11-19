<?php

declare(strict_types=1);

namespace Craft\Assistant\Retrieval;

interface Vectorizer
{
    /**
     * Creates the embedding vector for a text.
     *
     * @return float[]
     */
    public function vectorize(string $text): array;
}
