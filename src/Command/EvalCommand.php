<?php

declare(strict_types=1);

namespace Craft\Assistant\Command;

use Craft\Assistant\Ai\CitationChecker;
use Craft\Assistant\Ai\QueryAnswerer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Regression set for the assistant.
 *
 * The command checks four things:
 *  1. Does the search find the right service?
 *  2. Does the answer name that service?
 *  3. Does the assistant decline when there is no data?
 *  4. Does it name identifiers that do not exist?
 *
 * Point 4 is the hallucination rate — it has to be zero. That is the core of
 * the claim "the model does not calculate, it looks things up".
 */
#[AsCommand(name: 'app:eval', description: 'Runs the assistant against the regression set.')]
final class EvalCommand extends Command
{
    /** @var list<array{question: string, expected: ?string, decline: bool}> */
    private const QUESTIONS = [
        ['question' => 'What does the tiling work in the bathroom cost per square metre?', 'expected' => 'S-1-01', 'decline' => false],
        ['question' => 'How much does a heat exchanger cost?', 'expected' => 'S-2-03', 'decline' => false],
        ['question' => 'Cost of mounting a radiator', 'expected' => 'S-2-01', 'decline' => false],
        ['question' => 'What does it cost to install a washout WC?', 'expected' => 'S-1-05', 'decline' => false],
        ['question' => 'What does a distribution board cost?', 'expected' => 'S-3-01', 'decline' => false],
        ['question' => 'Price per cubic metre for building rubble disposal', 'expected' => 'S-4-01', 'decline' => false],
        ['question' => 'How much does it cost to lay the cables?', 'expected' => 'S-3-02', 'decline' => false],
        ['question' => 'What does core drilling cost?', 'expected' => 'S-5-01', 'decline' => false],
        ['question' => 'Cost of silicone joints', 'expected' => 'S-1-09', 'decline' => false],
        ['question' => 'How expensive is the isolation valve?', 'expected' => 'S-1-07', 'decline' => false],
        // Questions for which there is no data: here the assistant must decline.
        ['question' => 'What does it cost to build a passive house?', 'expected' => null, 'decline' => true],
        ['question' => 'Price for a crane with 30 tonnes of lifting capacity?', 'expected' => null, 'decline' => true],
        ['question' => 'How much does a roofer earn in this business?', 'expected' => null, 'decline' => true],
    ];

    public function __construct(
        private QueryAnswerer $answerer,
        private CitationChecker $citationChecker,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Regression set of the assistant');

        $rows = [];
        $found = 0;
        $cited = 0;
        $declined = 0;
        $expectedDeclines = 0;
        $warnings = 0;

        foreach (self::QUESTIONS as $entry) {
            $answer = $this->answerer->answer($entry['question']);

            $identifiers = array_column($answer->sources, 'id');
            $used = array_values(array_filter(
                $identifiers,
                static fn (string $id): bool => null !== $entry['expected'] && $id === $entry['expected'],
            ));
            $hasFound = [] !== $used;

            // The citation rate is the stricter check: it shows whether the
            // answer names the right service — not only whether the search
            // found it.
            $citedIdentifiers = $this->citationChecker->usedIdentifiers(
                $answer->text,
                null !== $entry['expected'] ? [$entry['expected']] : [],
            );
            $hasCited = [] !== $citedIdentifiers;

            $hasDeclined = $answer->declined || [] === $answer->sources;

            if (null !== $entry['expected']) {
                $found += $hasFound ? 1 : 0;
                $cited += $hasCited ? 1 : 0;
            } else {
                ++$expectedDeclines;
                $declined += $hasDeclined ? 1 : 0;
            }

            $warnings += \count($answer->warnings);

            $rows[] = [
                $entry['question'],
                $entry['expected'] ?? '(decline expected)',
                null === $entry['expected']
                    ? ($hasDeclined ? '<fg=green>yes</>' : '<fg=red>no</>')
                    : ($hasFound ? '<fg=green>yes</>' : '<fg=red>no</>'),
                null === $entry['expected']
                    ? '—'
                    : ($hasCited ? '<fg=green>yes</>' : '<fg=red>no</>'),
                \count($answer->warnings) > 0 ? '<fg=red>'.implode('; ', $answer->warnings).'</>' : '<fg=green>none</>',
            ];
        }

        $withExpectation = \count(self::QUESTIONS) - $expectedDeclines;

        $io->table(['Question', 'Expected', 'Found', 'Cited', 'Hallucinations'], $rows);

        $io->definitionList(
            ['Hit rate (search)' => sprintf('%d/%d (%.0f %%)', $found, $withExpectation, $withExpectation > 0 ? $found / $withExpectation * 100 : 0)],
            ['Citation rate (answer)' => sprintf('%d/%d (%.0f %%)', $cited, $withExpectation, $withExpectation > 0 ? $cited / $withExpectation * 100 : 0)],
            ['Decline rate' => sprintf('%d/%d (%.0f %%)', $declined, $expectedDeclines, $expectedDeclines > 0 ? $declined / $expectedDeclines * 100 : 0)],
            ['Hallucinations total' => (string) $warnings],
        );

        if ($warnings > 0) {
            $io->warning('Unverifiable identifiers were reported — the hallucination rate is not zero.');
        } else {
            $io->success('No unverifiable identifiers — the model did not invent anything.');
        }

        return $warnings > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
