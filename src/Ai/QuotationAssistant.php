<?php

declare(strict_types=1);

namespace Craft\Assistant\Ai;

use Craft\Assistant\Domain\Money;
use Craft\Assistant\Entity\AuditEntry;
use Craft\Assistant\Entity\QuotationDraft;
use Craft\Assistant\Repository\QuotationDraftRepository;
use Craft\Assistant\Retrieval\ServiceSearcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Lock\LockFactory;

/**
 * Mode B — quotation draft.
 *
 * The model assigns the order to services. The arithmetic afterwards is done
 * by the calculator, without the model. Draft and approval are kept apart: the
 * model may never approve anything itself.
 */
final readonly class QuotationAssistant
{
    public function __construct(
        private ChatClient $chat,
        private ServiceSearcher $searcher,
        private QuotationParser $parser,
        private QuotationDraftRepository $drafts,
        private EntityManagerInterface $entityManager,
        private LockFactory $lockFactory,
    ) {
    }

    public function createDraft(
        string $order,
        string $customer,
        string $project,
        string $user = 'office',
    ): Answer {
        $matches = $this->searcher->search($order, 10);

        if ([] === $matches) {
            return new Answer(
                text: sprintf(
                    "For \"%s\" I cannot find a matching position in the service catalogue.\n\n"
                    ."Without an assignment I cannot calculate a quotation. Please name the service "
                    ."numbers or describe the work in more detail.",
                    $order,
                ),
                declined: true,
            );
        }

        $context = $this->buildContext($matches, $order);
        $rawText = $this->chat->ask(Prompts::quotationDraft(), $context);

        $parsed = $this->parser->parse($rawText);
        $lines = $parsed['lines'];
        $warnings = [];

        foreach ($parsed['unknown'] as $number) {
            $warnings[] = sprintf(
                'The line "%s" was discarded — there is no service with that number in the catalogue.',
                $number,
            );
        }

        if ([] === $lines) {
            return new Answer(
                text: 'The assignment by the model was not usable. Please check the assignment or create it manually.',
                warnings: [] !== $warnings ? $warnings : ['The model returned no usable lines.'],
                declined: true,
            );
        }

        // Numbering and storing run under a lock: without it, two parallel
        // requests get the same number and the second one fails on the
        // uniqueness check.
        $lock = $this->lockFactory->createLock('craft.quotation-draft', 10.0);
        $lock->acquire(true);

        try {
            $draft = new QuotationDraft(
                $this->drafts->nextNumber(),
                $customer,
                $project,
            );

            $total = Money::fromEuro(0);
            $descriptions = [];

            foreach ($lines as $line) {
                $draft->addLine($line);
                $total = $total->plus(Money::fromEuro($line->totalNet()));
                $descriptions[] = sprintf(
                    '- %s %s × %s %s = %s € net%s',
                    $line->serviceNumber(),
                    $line->text(),
                    $line->quantity(),
                    $line->unit(),
                    Money::fromEuro($line->totalNet())->euro(),
                    null !== $line->remark() ? ' (remark: '.$line->remark().')' : '',
                );

                // Lines that cannot be calculated completely are reported, not
                // only written into the running text.
                if (null !== $line->remark()) {
                    $warnings[] = sprintf(
                        'Line %s (%s) cannot be calculated completely: %s',
                        $line->serviceNumber(),
                        $line->text(),
                        $line->remark(),
                    );
                }
            }

            $draft->setGrandTotal($total->decimal());
            $draft->setNote(sprintf('Draft created from order: %s', $order));

            $this->entityManager->persist($draft);
            $this->entityManager->persist(new AuditEntry(
                'quotation_draft_created',
                $user,
                'quotation_draft:'.$draft->number(),
                [
                    'customer' => $customer,
                    'project' => $project,
                    'lines' => count($lines),
                    'grand_total' => $total->decimal(),
                ],
            ));
            $this->entityManager->flush();
        } finally {
            $lock->release();
        }

        $sources = [];
        foreach ($lines as $line) {
            $sources[] = [
                'id' => $line->serviceNumber(),
                'label' => $line->text(),
                'origin' => $line->priceSnapshot(),
            ];
        }

        return new Answer(
            text: sprintf(
                "Quotation draft %s was created (customer: %s, project: %s).\n\n%s\n\nNet total: %s €\n\n"
                ."The draft is not approved yet — approval is done by the office.",
                $draft->number(),
                $customer,
                $project,
                implode("\n", $descriptions),
                $total->euro(),
            ),
            sources: $sources,
            warnings: $warnings,
            draftId: $draft->id(),
        );
    }

    /**
     * @param list<array{service: \Craft\Assistant\Entity\Service, score: float, priceSnapshot: ?\Craft\Assistant\Domain\PriceSnapshot}> $matches
     */
    private function buildContext(array $matches, string $order): string
    {
        $lines = [];
        foreach ($matches as $match) {
            $line = sprintf(
                '- %s | %s | Unit: %s | Calculation: %s',
                $match['service']->serviceNumber(),
                $match['service']->text(),
                $match['service']->unit(),
                $match['service']->calculationType()->label(),
            );

            if (null !== $match['service']->defaultMaterial()) {
                // The material name has to be in the context exactly, otherwise
                // the calculation cannot find it.
                $line .= sprintf(' | Default material: %s', $match['service']->defaultMaterial());
            }

            $lines[] = $line;
        }

        return sprintf(
            "SERVICE CATALOGUE (only these positions are allowed):\n%s\n\nORDER:\n%s",
            implode("\n", $lines),
            $order,
        );
    }
}
