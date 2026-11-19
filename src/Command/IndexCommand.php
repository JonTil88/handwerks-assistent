<?php

declare(strict_types=1);

namespace Craft\Assistant\Command;

use Craft\Assistant\Entity\Material;
use Craft\Assistant\Entity\Service;
use Craft\Assistant\Retrieval\EmbeddingStore;
use Craft\Assistant\Retrieval\Vectorizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Computes the embeddings for services and materials and stores them in the
 * vector table. The command is idempotent: it overwrites existing vectors.
 */
#[AsCommand(name: 'app:index', description: 'Computes the embeddings for services and materials (pgvector).')]
final class IndexCommand extends Command
{
    public function __construct(
        private Vectorizer $vectorizer,
        private EmbeddingStore $embeddingStore,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $count = 0;
        $errors = 0;

        foreach ($this->entityManager->getRepository(Service::class)->findAll() as $service) {
            // The service number is irrelevant for the semantics (S-1-01 says
            // nothing), the service text, the default material and the keywords
            // say everything.
            $text = sprintf(
                '%s. Trade: %s. Unit: %s.%s%s',
                $service->text(),
                $service->trade(),
                $service->unit(),
                null !== $service->defaultMaterial() ? ' Material: '.$service->defaultMaterial().'.' : '',
                null !== $service->keywords() ? ' Terms: '.$service->keywords().'.' : '',
            );

            try {
                $this->embeddingStore->store('service', (int) $service->id(), $this->vectorizer->vectorize($text));
                ++$count;
            } catch (\Throwable $e) {
                ++$errors;
                $io->warning(sprintf('Service %s: %s', $service->serviceNumber(), $e->getMessage()));
            }
        }

        foreach ($this->entityManager->getRepository(Material::class)->findAll() as $material) {
            $text = sprintf('Material: %s | Unit: %s | Category: %s', $material->label(), $material->unit(), $material->category());

            try {
                $this->embeddingStore->store('material', (int) $material->id(), $this->vectorizer->vectorize($text));
                ++$count;
            } catch (\Throwable $e) {
                ++$errors;
                $io->warning(sprintf('Material %s: %s', $material->label(), $e->getMessage()));
            }
        }

        $io->success(sprintf('%d vectors written, %d errors.', $count, $errors));

        return Command::SUCCESS;
    }
}
