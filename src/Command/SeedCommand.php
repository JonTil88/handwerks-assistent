<?php

declare(strict_types=1);

namespace Craft\Assistant\Command;

use Craft\Assistant\Entity\AuditEntry;
use Craft\Assistant\Entity\CalculationType;
use Craft\Assistant\Entity\Machine;
use Craft\Assistant\Entity\Material;
use Craft\Assistant\Entity\MaterialPrice;
use Craft\Assistant\Entity\Service;
use Craft\Assistant\Entity\Subcontractor;
use Craft\Assistant\Entity\WageRate;
use Craft\Assistant\Retrieval\EmbeddingStore;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Fills the database with a synthetic sample master data set of a trade
 * business. All values are invented and serve the demonstration.
 */
#[AsCommand(name: 'app:seed', description: 'Fills the database with the synthetic sample master data.')]
final class SeedCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EmbeddingStore $embeddingStore,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite existing data without asking.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ([] !== $this->entityManager->getRepository(Service::class)->findAll()) {
            $forced = (bool) $input->getOption('force');
            if (!$forced && (!$input->isInteractive() || !$io->confirm('Data is already present. Overwrite?', false))) {
                $io->warning('Aborted — no data was changed.');

                return Command::SUCCESS;
            }

            $this->entityManager->createQuery('DELETE FROM '.Service::class)->execute();
            $this->entityManager->createQuery('DELETE FROM '.Material::class)->execute();
            $this->entityManager->createQuery('DELETE FROM '.WageRate::class)->execute();
            $this->entityManager->createQuery('DELETE FROM '.Subcontractor::class)->execute();
            $this->entityManager->createQuery('DELETE FROM '.Machine::class)->execute();
            $this->entityManager->flush();

            // The vector index is cleared as well: the vectors are linked by
            // id, and the ids are assigned anew. Without clearing, a vector
            // would point at a different text.
            $this->embeddingStore->clearIndex();
        }

        $today = new \DateTimeImmutable();

        // ---- Wage rates ----
        foreach ([
            ['Skilled worker', '42.50'],
            ['Master craftsman', '58.00'],
            ['Apprentice', '18.00'],
            ['Helper', '32.00'],
            ['Foreman', '52.00'],
        ] as [$role, $rate]) {
            $this->entityManager->persist(new WageRate($role, $rate, $today->modify('-6 months')));
        }

        // ---- Subcontractors ----
        foreach ([
            ['Sanitary Construction Ltd', 'Sanitary', '68.00'],
            ['Heating Systems Kruse', 'Heating', '74.00'],
            ['Electrical Petzold', 'Electrical', '66.00'],
            ['Tiling Riedel', 'Sanitary', '58.00'],
            ['Pipework North Ltd', 'Sanitary', '62.00'],
        ] as [$company, $trade, $rate]) {
            $this->entityManager->persist(new Subcontractor($company, $trade, $rate, $today->modify('-6 months')));
        }

        // ---- Machines ----
        foreach ([
            ['Core drilling', '45.00'],
            ['Tile cutter', '28.00'],
            ['Thermal imaging camera', '35.00'],
            ['Pipe press tool', '52.00'],
        ] as [$label, $rate]) {
            $this->entityManager->persist(new Machine($label, $rate, $today->modify('-6 months')));
        }

        // ---- Materials with price snapshots ----
        $materials = [
            ['Ceramic tile 30x60 white', 'm²', 'Tiles', '24.50', '-30 days', 'Supplier quotation L-2026-014'],
            ['Ceramic tile 60x60 matt', 'm²', 'Tiles', '39.90', '-30 days', 'Supplier quotation L-2026-014'],
            ['Washout WC', 'each', 'Sanitary fittings', '189.00', '-45 days', 'Supplier quotation L-2026-009'],
            ['Washbasin ceramic', 'each', 'Sanitary fittings', '149.00', '-45 days', 'Supplier quotation L-2026-009'],
            ['Angle valve 1/2 inch', 'each', 'Valves', '7.90', '-60 days', 'Supplier quotation L-2026-007'],
            ['Copper pipe 15 mm', 'm', 'Pipe', '12.40', '-60 days', 'Supplier quotation L-2026-007'],
            ['PE pipe 20 mm', 'm', 'Pipe', '5.80', '-60 days', 'Supplier quotation L-2026-007'],
            ['Isolation valve DN15', 'each', 'Valves', '34.00', '-60 days', 'Supplier quotation L-2026-007'],
            ['Silicone sanitary', 'each', 'Consumables', '6.40', '-90 days', 'Supplier quotation L-2026-003'],
            ['Sealing compound', 'pack', 'Consumables', '18.50', '-90 days', 'Supplier quotation L-2026-003'],
            ['Heat exchanger 24 kW', 'each', 'Heating equipment', '1249.00', '-20 days', 'Supplier quotation L-2026-021'],
            ['Radiator 500x1000', 'each', 'Heating equipment', '189.00', '-90 days', 'Supplier quotation L-2026-005'],
            ['Pump group', 'each', 'Heating accessories', '349.00', '-20 days', 'Supplier quotation L-2026-021'],
            ['Heating pipe 22 mm', 'm', 'Heating accessories', '14.20', '-90 days', 'Supplier quotation L-2026-005'],
            ['Safety valve', 'each', 'Heating accessories', '58.00', '-120 days', 'Supplier quotation L-2026-002'],
            ['Expansion vessel 12 l', 'each', 'Heating accessories', '89.00', '-120 days', 'Supplier quotation L-2026-002'],
            ['NYM-J 3x2.5', 'm', 'Electrical material', '4.90', '-75 days', 'Supplier quotation L-2026-011'],
            ['Distribution board 24-way', 'each', 'Electrical material', '149.00', '-75 days', 'Supplier quotation L-2026-011'],
            ['Circuit breaker 16 A', 'each', 'Electrical material', '21.90', '-75 days', 'Supplier quotation L-2026-011'],
            ['Flush-mounted socket', 'each', 'Electrical material', '5.90', '-75 days', 'Supplier quotation L-2026-011'],
            ['RCD 40 A / 30 mA', 'each', 'Electrical material', '89.00', '-75 days', 'Supplier quotation L-2026-011'],
            ['Cable duct 40x40', 'm', 'Electrical material', '3.40', '-75 days', 'Supplier quotation L-2026-011'],
            ['Building rubble container 3 m³', 'each', 'Disposal', '480.00', '-40 days', 'Disposal contract E-2026-03'],
            ['Residual waste disposal', 'm³', 'Disposal', '65.00', '-40 days', 'Disposal contract E-2026-03'],
            // Deliberately an old price snapshot: the warning about a stale
            // price is demonstrable in the demo.
            ['Aluminium edge trim 8 mm', 'm', 'Consumables', '2.10', '-400 days', 'Supplier quotation L-2025-018'],
        ];

        foreach ($materials as [$label, $unit, $category, $price, $age, $source]) {
            $material = new Material($label, $unit, $category);
            $material->addPrice(new MaterialPrice($price, $today->modify($age), $source));
            $this->entityManager->persist($material);
        }

        // A material without any price snapshot: here the system has to stay honest.
        $this->entityManager->persist(new Material('Aluminium edge trim 10 mm', 'm', 'Consumables'));

        // ---- Service catalogue ----
        // Structure: number, text, trade, unit, calculation type, list price,
        //            markup, hours per unit, default material, keywords
        $services = [
            ['S-1-01', 'Lay ceramic tiles', 'Sanitary', 'm²', CalculationType::Material, '42.00', '12.00', '0.800', 'Ceramic tile 30x60 white', 'tiles tiling ceramic floor wall'],
            ['S-1-02', 'Prepare and level the substrate', 'Sanitary', 'm²', CalculationType::Labour, null, '12.00', '0.500', null, 'substrate levelling screed filler'],
            ['S-1-03', 'Replace copper pipework', 'Sanitary', 'm', CalculationType::Labour, null, '12.00', '0.700', null, 'pipework copper water pipe plumbing'],
            ['S-1-04', 'Connect the water pipe', 'Sanitary', 'each', CalculationType::Labour, null, '12.00', '0.900', null, 'water pipe connection potable water'],
            ['S-1-05', 'Install and connect WC', 'Sanitary', 'each', CalculationType::Labour, null, '12.00', '1.600', null, 'wc toilet washout ceramic pan cistern'],
            ['S-1-06', 'Install washbasin', 'Sanitary', 'each', CalculationType::Labour, null, '12.00', '0.800', null, 'washbasin mirror basin'],
            ['S-1-07', 'Replace angle valves and connections', 'Sanitary', 'each', CalculationType::Material, '26.00', '12.00', '0.400', 'Angle valve 1/2 inch', 'angle valve stop valve isolation'],
            ['S-1-08', 'Seal floor and walls', 'Sanitary', 'm²', CalculationType::Material, '18.50', '12.00', '0.350', 'Sealing compound', 'sealing waterproofing tanking'],
            ['S-1-09', 'Seal joints with silicone', 'Sanitary', 'm', CalculationType::Labour, null, '12.00', '0.150', null, 'silicone sealant joint'],
            ['S-1-10', 'Remove the old installation', 'Sanitary', 'm', CalculationType::Labour, null, '12.00', '0.900', null, 'dismantling demolition removal'],
            // Uses the old price snapshot on purpose: the warning about a stale
            // price is demonstrable in the demo.
            ['S-1-11', 'Fit edge trim', 'Sanitary', 'm', CalculationType::Material, '3.10', '12.00', '0.200', 'Aluminium edge trim 8 mm', 'edge trim profile rail'],
            ['S-2-01', 'Mount a radiator', 'Heating', 'each', CalculationType::Labour, null, '15.00', '1.400', null, 'radiator heater mounting'],
            ['S-2-02', 'Replace heating pipework', 'Heating', 'm', CalculationType::Labour, null, '15.00', '0.600', null, 'heating pipe pipework'],
            ['S-2-03', 'Supply and replace the heat exchanger', 'Heating', 'each', CalculationType::Material, '1249.00', '15.00', '4.000', 'Heat exchanger 24 kW', 'heat exchanger boiler heating'],
            ['S-2-04', 'Connect the pump group', 'Heating', 'each', CalculationType::Labour, null, '15.00', '1.200', null, 'pump group circulation pump'],
            ['S-2-05', 'Check safety valve and expansion vessel', 'Heating', 'each', CalculationType::Labour, null, '15.00', '0.700', null, 'safety valve expansion vessel heating check'],
            ['S-2-06', 'Run the heating flush', 'Heating', 'each', CalculationType::Labour, null, '15.00', '1.100', null, 'heating flush cleaning'],
            ['S-2-07', 'Clean and bleed the radiator', 'Heating', 'each', CalculationType::Labour, null, '15.00', '0.400', null, 'radiator cleaning bleeding'],
            ['S-2-08', 'Emergency call-out heating pipe burst', 'Heating', 'hour', CalculationType::CompositePrice, '95.00', '0.00', '1.000', null, 'emergency callout urgent'],
            ['S-3-01', 'Renew the distribution board', 'Electrical', 'each', CalculationType::Labour, null, '18.00', '3.200', null, 'distribution board consumer unit'],
            ['S-3-02', 'Lay the cables', 'Electrical', 'm', CalculationType::Material, '11.40', '18.00', '0.350', 'NYM-J 3x2.5', 'cable wiring laying electrical'],
            ['S-3-03', 'Fit sockets and switches', 'Electrical', 'each', CalculationType::Labour, null, '18.00', '0.500', null, 'socket switch fitting electrical'],
            ['S-3-04', 'Test and document RCD and MCB', 'Electrical', 'each', CalculationType::Labour, null, '18.00', '0.600', null, 'rcd mcb test inspection electrical'],
            ['S-3-05', 'Renew the circuit breaker', 'Electrical', 'each', CalculationType::Material, '34.50', '18.00', '0.400', 'Circuit breaker 16 A', 'circuit breaker mcb automatic'],
            ['S-3-06', 'Lay the cable duct', 'Electrical', 'm', CalculationType::Labour, null, '18.00', '0.250', null, 'cable duct trunking'],
            ['S-3-07', 'Lay the empty conduit', 'Electrical', 'm', CalculationType::Labour, null, '18.00', '0.200', null, 'empty conduit pipe electrical'],
            ['S-3-08', 'Connect the ceiling luminaire', 'Electrical', 'each', CalculationType::Labour, null, '18.00', '0.450', null, 'ceiling light luminaire lamp'],
            ['S-3-09', 'Fit a flush-mounted socket', 'Electrical', 'each', CalculationType::Material, '11.80', '18.00', '0.300', 'Flush-mounted socket', 'socket flush mounting electrical'],
            ['S-4-01', 'Dispose of building rubble', 'Disposal', 'm³', CalculationType::Disposal, '480.00', '0.00', '0.000', 'Building rubble container 3 m³', 'rubble disposal waste container'],
            ['S-4-02', 'Dispose of residual waste', 'Disposal', 'm³', CalculationType::Disposal, '65.00', '0.00', '0.000', 'Residual waste disposal', 'residual waste disposal rubbish'],
            ['S-5-01', 'Core drill', 'Sanitary', 'each', CalculationType::Machine, null, '12.00', '0.500', null, 'core drilling hole'],
            ['S-5-02', 'Cut tiles', 'Sanitary', 'm', CalculationType::Machine, null, '12.00', '0.100', null, 'tile cutting cutting'],
        ];

        foreach ($services as [$number, $text, $trade, $unit, $type, $listPrice, $markup, $hours, $material, $keywords]) {
            $this->entityManager->persist(new Service(
                $number,
                $text,
                $trade,
                $unit,
                $type,
                $listPrice,
                $markup,
                $hours,
                $material,
                $keywords,
            ));
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            'Sample master data created: %d services, %d materials, %d wage rates, %d subcontractors, %d machines.',
            \count($services),
            \count($materials) + 1,
            5,
            5,
            4,
        ));
        $io->note('All values are synthetic and serve the demonstration only.');

        return Command::SUCCESS;
    }
}
