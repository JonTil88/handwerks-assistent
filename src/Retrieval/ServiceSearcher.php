<?php

declare(strict_types=1);

namespace Craft\Assistant\Retrieval;

use Craft\Assistant\Domain\MasterDataResolver;
use Craft\Assistant\Domain\PriceSnapshot;
use Craft\Assistant\Entity\Service;
use Craft\Assistant\Repository\ServiceRepository;

/**
 * Hybrid search: word matches (text, keywords, default material) plus vector
 * similarity (pgvector).
 *
 * Lesson from the regression set: pure vector similarity does not separate
 * "fits" from "does not fit" — an 8B embedding model returns similar values
 * for off-topic questions. Word matches are therefore the leading signal; the
 * vector search adds linguistic variants.
 */
final readonly class ServiceSearcher
{
    /** Words that carry no information for the assignment. */
    private const STOP_WORDS = [
        'what', 'how', 'much', 'costs', 'cost', 'price', 'the', 'and', 'or', 'for', 'with', 'without',
        'per', 'each', 'a', 'an', 'one', 'in', 'on', 'at', 'to', 'from', 'is', 'are', 'am', 'do', 'does',
        'need', 'needs', 'make', 'makes', 'create', 'creates', 'calculate', 'calculates', 'please',
        'this', 'that', 'these', 'those', 'our', 'my', 'your', 'no', 'not', 'never', 'always', 'here',
    ];

    /**
     * Minimum score for a hit that was found by vector similarity only.
     * Word matches are valid regardless of their score.
     */
    private const MIN_VECTOR_SCORE = 0.66;

    /** Word matches get a score of their own so they rank first. */
    private const SCORE_WORD = 0.72;
    private const SCORE_EXACT = 1.0;
    private const WEIGHT_WORD = 0.15;

    public function __construct(
        private Vectorizer $vectorizer,
        private ServiceRepository $services,
        private MasterDataResolver $masterData,
    ) {
    }

    /**
     * @return list<array{service: Service, score: float, priceSnapshot: ?PriceSnapshot}>
     */
    public function search(string $term, int $limit = 8, ?\DateTimeImmutable $asOf = null): array
    {
        $asOf ??= new \DateTimeImmutable();

        // Without a search term there is nothing to search. Without this check
        // every entry would count as an exact match (stripos with an empty
        // needle returns 0, not false).
        $term = trim($term);
        if ('' === $term) {
            return [];
        }

        $candidates = [];
        $words = $this->significantWords($term);
        $all = $this->services->findBy(['active' => true]);

        // 1. Exact text or number match
        foreach ($this->textMatches($term, $all) as $service) {
            $candidates[$service->id()] = ['service' => $service, 'score' => self::SCORE_EXACT, 'word' => true];
        }

        // 2. Word matches in text, keywords and default material
        foreach ($all as $service) {
            $id = $service->id();
            if (isset($candidates[$id])) {
                continue;
            }
            if ($this->wordBonus($words, $service) > 0.0) {
                $candidates[$id] = ['service' => $service, 'score' => self::SCORE_WORD, 'word' => true];
            }
        }

        // If not a single word of the question occurs anywhere in the
        // catalogue, the question is off-topic. Vector similarity cannot
        // decide that — it happily ranks "dispose of building rubble" for
        // "build a passive house" — so the model is not asked at all.
        if ([] === $candidates) {
            return [];
        }

        // 3. Vector similarity, extended by the word bonus
        try {
            $vector = $this->vectorizer->vectorize($term);
            foreach ($this->services->searchSimilar($vector, $limit) as $match) {
                $service = $match['service'];
                $id = $service->id();
                $bonus = $this->wordBonus($words, $service);
                $score = $match['score'] + $bonus;

                if (!isset($candidates[$id]) || $candidates[$id]['score'] < $score) {
                    $candidates[$id] = ['service' => $service, 'score' => $score, 'word' => $bonus > 0.0];
                }
            }
        } catch (\Throwable) {
            // Without embeddings the word search remains — the answer gets
            // weaker, but never wrong. That is the intended degradation.
        }

        // 4. Keep only the hits that hold up
        $matches = [];
        foreach ($candidates as $entry) {
            if (!$entry['word'] && $entry['score'] < self::MIN_VECTOR_SCORE) {
                continue;
            }

            $matches[] = [
                'service' => $entry['service'],
                'score' => round($entry['score'], 4),
                'priceSnapshot' => $this->priceSnapshotFor($entry['service'], $asOf),
            ];
        }

        usort($matches, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return \array_slice($matches, 0, $limit);
    }

    private function priceSnapshotFor(Service $service, \DateTimeImmutable $asOf): ?PriceSnapshot
    {
        if (!\in_array($service->calculationType()->value, ['material', 'disposal'], true)) {
            return null;
        }

        // The price belongs to the default material of the service, not to its text.
        $label = $service->defaultMaterial() ?? $service->text();

        return $this->masterData->priceSnapshotForLabel($label, $asOf);
    }

    /**
     * @param list<Service> $all
     *
     * @return list<Service>
     */
    private function textMatches(string $term, array $all): array
    {
        $matches = [];

        foreach ($all as $service) {
            if (stripos($service->serviceNumber(), $term) !== false
                || stripos($service->text(), $term) !== false) {
                $matches[] = $service;
            }
        }

        return $matches;
    }

    /**
     * @param list<string> $words
     */
    private function wordBonus(array $words, Service $service): float
    {
        if ([] === $words) {
            return 0.0;
        }

        $haystack = $this->haystackFor($service);
        $hits = 0;

        foreach ($words as $word) {
            if ($this->wordMatches($word, $haystack)) {
                ++$hits;
            }
        }

        if (0 === $hits) {
            return 0.0;
        }

        return self::WEIGHT_WORD * ($hits / \count($words));
    }

    private function haystackFor(Service $service): string
    {
        return $this->normalize(sprintf(
            '%s %s %s %s %s',
            $service->text(),
            $service->trade(),
            $service->defaultMaterial() ?? '',
            $service->keywords() ?? '',
            $service->unit(),
        ));
    }

    /**
     * Does a query word match a text word?
     *
     * Two rules, both of them needed in practice:
     *
     * 1. The query word has to occur as a whole word. Otherwise "build"
     *    matches "building" and a question about a passive house finds
     *    "dispose of building rubble".
     * 2. Composites count: "tiling" matches "tiles", because the text word is
     *    a prefix of the query word. Otherwise a question about "tiling work"
     *    would not find "lay tiles".
     */
    private function wordMatches(string $word, string $haystack): bool
    {
        if (1 === preg_match('/\b'.preg_quote($word, '/').'\b/u', $haystack)) {
            return true;
        }

        foreach (explode(' ', $haystack) as $textWord) {
            $textWord = trim($textWord, ".,;:!?()[]\"'");
            if (mb_strlen($textWord) < 5) {
                continue;
            }
            if (str_starts_with($word, $textWord)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function significantWords(string $term): array
    {
        $normalized = $this->normalize($term);
        $normalized = str_replace('-', ' ', $normalized);

        preg_match_all('/[a-z0-9]{4,}/', $normalized, $matches);

        $words = [];
        foreach ($matches[0] ?? [] as $word) {
            if (\in_array($word, self::STOP_WORDS, true)) {
                continue;
            }
            $words[] = $word;
        }

        return array_values(array_unique($words));
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower($text);

        return str_replace(['ä', 'ö', 'ü', 'ß', '–', '—'], ['ae', 'oe', 'ue', 'ss', '-', '-'], $text);
    }
}
