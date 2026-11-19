<?php

declare(strict_types=1);

namespace Craft\Assistant\Ai;

use Craft\Assistant\Domain\MasterDataResolver;
use Craft\Assistant\Domain\PriceSnapshot;
use Craft\Assistant\Entity\Service;
use Craft\Assistant\Repository\MaterialRepository;
use Craft\Assistant\Retrieval\ServiceSearcher;

/**
 * Mode A — question answering.
 *
 * Flow: retrieval → context → model → citation check.
 *
 * The context contains master data and nothing else. If the retrieval finds
 * nothing, the model is not asked at all: the answer is "I have no data for
 * this". That is cheaper, faster and — above all — honest.
 */
final readonly class QueryAnswerer
{
    public function __construct(
        private ChatClient $chat,
        private ServiceSearcher $searcher,
        private MasterDataResolver $masterData,
        private MaterialRepository $materials,
        private CitationChecker $citationChecker,
    ) {
    }

    public function answer(string $question, ?\DateTimeImmutable $asOf = null): Answer
    {
        $asOf ??= new \DateTimeImmutable();
        $matches = $this->searcher->search($question, 8, $asOf);

        if ([] === $matches) {
            return new Answer(
                text: sprintf(
                    "There is no matching position in the service catalogue for \"%s\".\n\n"
                    ."I work exclusively with the master data of this business and I will not guess "
                    ."a service or a price. Please name the service number (e.g. S-3-01) or describe "
                    ."the work in more detail.",
                    $question,
                ),
                declined: true,
            );
        }

        $context = $this->buildContext($matches, $question, $asOf);
        $answer = $this->chat->ask(Prompts::query(), $context['text']);

        $identifiers = array_map(
            static fn (array $m): string => $m['service']->serviceNumber(),
            $matches,
        );

        $warnings = $this->citationChecker->check($answer, $identifiers, $context['sources']);

        $sources = [];
        foreach ($matches as $match) {
            $sources[] = [
                'id' => $match['service']->serviceNumber(),
                'label' => $match['service']->text(),
                'origin' => $match['priceSnapshot'] instanceof PriceSnapshot
                    ? $match['priceSnapshot']->origin()
                    : $match['service']->calculationType()->label(),
            ];
        }

        return new Answer(
            text: $answer,
            sources: $sources,
            warnings: $warnings,
        );
    }

    /**
     * @param list<array{service: Service, score: float, priceSnapshot: ?PriceSnapshot}> $matches
     *
     * @return array{text: string, sources: list<string>}
     */
    private function buildContext(array $matches, string $question, \DateTimeImmutable $asOf): array
    {
        $lines = [];
        $sources = [];

        foreach ($matches as $match) {
            $service = $match['service'];
            $line = sprintf(
                '- %s | %s | Trade: %s | Unit: %s | Calculation: %s',
                $service->serviceNumber(),
                $service->text(),
                $service->trade(),
                $service->unit(),
                $service->calculationType()->label(),
            );

            if (null !== $match['priceSnapshot']) {
                $line .= sprintf(
                    ' | Price snapshot: %s per %s (valid from %s, source: %s)',
                    $match['priceSnapshot']->price->euroWithSymbol(),
                    $service->unit(),
                    $match['priceSnapshot']->validFrom->format('d.m.Y'),
                    $match['priceSnapshot']->source,
                );
                $sources[] = $match['priceSnapshot']->source;

                if ($match['priceSnapshot']->stale) {
                    $line .= ' | PRICE SNAPSHOT STALE';
                }
            } elseif (\in_array($service->calculationType()->value, ['material', 'disposal'], true)) {
                $line .= ' | NO VALID PRICE SNAPSHOT AVAILABLE';
            }

            if (null !== $service->listPrice()) {
                $line .= sprintf(' | List price: %s %s', $service->listPrice(), $service->unit());
            }

            $lines[] = $line;
        }

        // Materials with a current price snapshot, related to the topic
        foreach ($this->materials->searchByLabel($this->termFromQuestion($question), 6) as $material) {
            $snapshot = $this->masterData->priceSnapshotForMaterial($material, $asOf);
            if (null === $snapshot) {
                $lines[] = sprintf(
                    '- Material %s (%s) | NO VALID PRICE SNAPSHOT AVAILABLE',
                    $material->label(),
                    $material->unit(),
                );
                continue;
            }

            $lines[] = sprintf(
                '- Material %s | %s per %s | valid from %s | source: %s',
                $material->label(),
                $snapshot->price->euroWithSymbol(),
                $material->unit(),
                $snapshot->validFrom->format('d.m.Y'),
                $snapshot->source,
            );

            // The origin from the material list is an origin too, not a service
            // number — otherwise it would be counted as a hallucination.
            $sources[] = $snapshot->source;
        }

        return [
            'text' => sprintf(
                "MASTER DATA (the only admissible source of knowledge):\n%s\n\nQUESTION:\n%s",
                implode("\n", $lines),
                $question,
            ),
            'sources' => array_values(array_unique($sources)),
        ];
    }

    /**
     * The search term from the question: the longest word with at least four
     * characters.
     */
    private function termFromQuestion(string $question): string
    {
        preg_match_all('/[\p{L}\p{N}]{4,}/u', $question, $words);

        $candidates = $words[0] ?? [];
        if ([] === $candidates) {
            return '';
        }

        usort($candidates, static fn (string $a, string $b): int => \strlen($b) <=> \strlen($a));

        return $candidates[0];
    }
}
