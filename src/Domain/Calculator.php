<?php

declare(strict_types=1);

namespace Craft\Assistant\Domain;

use Craft\Assistant\Entity\CalculationType;
use Craft\Assistant\Entity\DraftLine;
use Craft\Assistant\Entity\Service;
use Craft\Assistant\Repository\MachineRepository;
use Craft\Assistant\Repository\SubcontractorRepository;
use Craft\Assistant\Repository\WageRateRepository;

/**
 * The deterministic calculation.
 *
 * This class is the heart of the claim "the model does not calculate": all
 * arithmetic happens here — without a model, without randomness. Every figure
 * that comes out carries the origin it was derived from.
 */
final readonly class Calculator
{
    public function __construct(
        private MasterDataResolver $masterData,
        private WageRateRepository $wageRates,
        private SubcontractorRepository $subcontractors,
        private MachineRepository $machines,
    ) {}

    /**
     * @param array{
     *     quantity?: float,
     *     hours?: float|null,
     *     role?: string,
     *     material?: string|null,
     *     machine?: string|null,
     *     subcontractor?: string|null
     * } $options
     */
    public function calculate(
        Service $service,
        array $options = [],
        ?\DateTimeImmutable $asOf = null,
    ): DraftLine {
        $asOf ??= new \DateTimeImmutable();

        // The options are normalised once, centrally. After that every part of
        // the domain logic reads the normalised values only — otherwise the
        // displayed quantity and the calculated costs drift apart (quantity 1,
        // costs for −5).
        [$options, $corrections] = $this->normalizeOptions($options);
        $quantity = (float) $options['quantity'];

        $line = new DraftLine(
            $service->serviceNumber(),
            $service->text(),
            $service->unit(),
            $service->calculationType(),
        );
        $line->setQuantity(number_format($quantity, 3, '.', ''));
        $line->setMarkupPercent($service->markupPercent());

        $origins = [];

        match ($service->calculationType()) {
            CalculationType::Labour => $this->calculateLabour($line, $service, $options, $asOf, $origins),
            CalculationType::Subcontractor => $this->calculateSubcontractor($line, $service, $options, $asOf, $origins),
            CalculationType::Machine => $this->calculateMachine($line, $service, $options, $asOf, $origins),
            CalculationType::Material, CalculationType::Disposal => $this->calculateMaterial($line, $service, $options, $asOf, $origins),
            CalculationType::CompositePrice => $this->calculateComposite($line, $service, $quantity, $origins),
        };

        $subtotal = $this->costSum($line);
        $markup = $subtotal->multiplyByPercent((float) $service->markupPercent());
        $total = $subtotal->plus($markup);

        $line->setTotalNet($total->decimal());
        $line->setUnitPrice($total->multiply(1.0 / $quantity)->decimal());

        if ([] !== $origins) {
            $line->setPriceSnapshot(implode('; ', $origins));
        }

        // Corrected inputs are reported, not silently accepted.
        foreach ($corrections as $correction) {
            $existing = $line->remark();
            $line->setRemark(null !== $existing ? $existing.' | '.$correction : $correction);
        }

        return $line;
    }

    /**
     * Normalises the inputs and collects notes about corrections.
     *
     * A quantity of zero or less is not an input but a mistake — it is set to
     * one and reported in the remark. Without this normalisation, negative
     * costs would end up in the total.
     *
     * @param array<string, mixed> $options
     *
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function normalizeOptions(array $options): array
    {
        $corrections = [];

        $quantity = (float) ($options['quantity'] ?? 1.0);
        if (!is_finite($quantity) || $quantity <= 0.0) {
            $corrections[] = isset($options['quantity'])
                ? sprintf('The quantity "%s" is unusable — calculated with 1 instead.', $this->formatNumber($options['quantity']))
                : 'No quantity was given — calculated with 1.';
            $quantity = 1.0;
        }
        $options['quantity'] = $quantity;

        // A working time of zero is no input either: the time stored with the
        // service applies instead.
        if (isset($options['hours'])) {
            $hours = (float) $options['hours'];
            if (!is_finite($hours) || $hours <= 0.0) {
                unset($options['hours']);
            }
        }

        return [$options, $corrections];
    }

    private function formatNumber(mixed $value): string
    {
        if (is_scalar($value)) {
            return (string) $value;
        }

        return \gettype($value);
    }

    /**
     * @param array<string, string> $origins
     */
    private function calculateLabour(
        DraftLine $line,
        Service $service,
        array $options,
        \DateTimeImmutable $asOf,
        array &$origins,
    ): void {
        $role = $options['role'] ?? 'Skilled worker';
        $hours = $this->hours($service, $options);
        $rate = $this->wageRates->rateForRole($role, $asOf);

        if (null === $rate) {
            $line->setRemark(sprintf('No valid wage rate for role "%s" as of %s.', $role, $asOf->format('d.m.Y')));
            $this->zeroCostComponent($line, 'Labour');

            return;
        }

        $wageRate = Money::fromEuro($rate->rate());
        $labour = $wageRate->multiply($hours);
        $line->setLabourCost($labour->decimal());
        $origins[] = sprintf(
            'Labour %s %s €/h (valid from %s)',
            $role,
            $wageRate->euro(),
            $rate->validFrom()->format('d.m.Y'),
        );
    }

    /**
     * @param array<string, string> $origins
     */
    private function calculateSubcontractor(
        DraftLine $line,
        Service $service,
        array $options,
        \DateTimeImmutable $asOf,
        array &$origins,
    ): void {
        $name = $options['subcontractor'] ?? null;
        $subcontractor = null;

        if (null !== $name) {
            foreach ($this->subcontractors->findBy(['company' => $name]) as $candidate) {
                if ($candidate->validFrom() <= $asOf) {
                    $subcontractor = $candidate;
                    break;
                }
            }
        }

        $subcontractor ??= $this->subcontractors->forTrade($service->trade(), $asOf);

        if (null === $subcontractor) {
            $line->setRemark(sprintf('No valid subcontractor for trade "%s" as of %s.', $service->trade(), $asOf->format('d.m.Y')));
            $this->zeroCostComponent($line, 'Subcontractor');

            return;
        }

        $hours = $this->hours($service, $options);
        $rate = Money::fromEuro($subcontractor->hourlyRate());
        $cost = $rate->multiply($hours);
        $line->setSubcontractorCost($cost->decimal());
        $origins[] = sprintf(
            'Subcontractor %s %s €/h (valid from %s)',
            $subcontractor->company(),
            $rate->euro(),
            $subcontractor->validFrom()->format('d.m.Y'),
        );
    }

    /**
     * @param array<string, string> $origins
     */
    private function calculateMachine(
        DraftLine $line,
        Service $service,
        array $options,
        \DateTimeImmutable $asOf,
        array &$origins,
    ): void {
        $name = $options['machine'] ?? null;
        $machine = null;

        foreach ($this->machines->findAll() as $candidate) {
            if (null !== $name && $candidate->label() !== $name) {
                continue;
            }
            if ($candidate->validFrom() <= $asOf) {
                $machine = $candidate;
                break;
            }
        }

        if (null === $machine) {
            $line->setRemark(sprintf('No valid machine rate as of %s.', $asOf->format('d.m.Y')));
            $this->zeroCostComponent($line, 'Machine');

            return;
        }

        $hours = $this->hours($service, $options);
        $rate = Money::fromEuro($machine->hourlyRate());
        $cost = $rate->multiply($hours);
        $line->setMachineCost($cost->decimal());
        $origins[] = sprintf(
            'Machine %s %s €/h (valid from %s)',
            $machine->label(),
            $rate->euro(),
            $machine->validFrom()->format('d.m.Y'),
        );
    }

    /**
     * @param array<string, string> $origins
     */
    private function calculateMaterial(
        DraftLine $line,
        Service $service,
        array $options,
        \DateTimeImmutable $asOf,
        array &$origins,
    ): void {
        $requested = $options['material'] ?? $service->defaultMaterial();
        $notes = [];

        if (null === $requested) {
            $line->setRemark('No material is stored for this line.');
            $this->zeroCostComponent($line, 'Material');

            return;
        }

        $snapshot = $this->masterData->priceSnapshotForLabel($requested, $asOf);

        // The material the price actually came from. It may differ from the
        // requested one — and then the origin has to say so.
        $pricedMaterial = $requested;

        if (null === $snapshot && null !== $service->defaultMaterial() && $requested !== $service->defaultMaterial()) {
            // The named material does not exist in the master data — the
            // service's default material applies, and the remark says so.
            $snapshot = $this->masterData->priceSnapshotForLabel($service->defaultMaterial(), $asOf);
            $pricedMaterial = $service->defaultMaterial();
            $notes[] = sprintf(
                'Material "%s" is not in the master data — calculated with the default material "%s".',
                $requested,
                $service->defaultMaterial(),
            );
        }

        if (null === $snapshot) {
            $line->setRemark(sprintf(
                'No valid price snapshot for "%s" as of %s — line cannot be calculated.',
                $requested,
                $asOf->format('d.m.Y'),
            ));
            $this->zeroCostComponent($line, 'Material');

            return;
        }

        if ($snapshot->stale) {
            $notes[] = sprintf(
                'The price snapshot for "%s" is from %s and therefore older than %d days.',
                $pricedMaterial,
                $snapshot->validFrom->format('d.m.Y'),
                MasterDataResolver::MAX_AGE_DAYS,
            );
        }

        $quantity = (float) $options['quantity'];
        $cost = $snapshot->price->multiply($quantity);

        if (CalculationType::Disposal === $service->calculationType()) {
            $line->setDisposalCost($cost->decimal());
        } else {
            $line->setMaterialCost($cost->decimal());
        }

        $origins[] = sprintf(
            '%s %s per %s (%s)',
            $pricedMaterial,
            $snapshot->price->euroWithSymbol(),
            $service->unit(),
            $snapshot->originWithNote(),
        );

        if ([] !== $notes) {
            $line->setRemark(implode(' | ', $notes));
        }
    }

    /**
     * @param array<string, string> $origins
     */
    private function calculateComposite(
        DraftLine $line,
        Service $service,
        float $quantity,
        array &$origins,
    ): void {
        if (null === $service->listPrice()) {
            $line->setRemark('No list price is stored for this line.');
            $this->zeroCostComponent($line, 'Material');

            return;
        }

        $price = Money::fromEuro($service->listPrice());
        $line->setMaterialCost($price->multiply($quantity)->decimal());
        $origins[] = sprintf('List price %s per %s', $price->euroWithSymbol(), $service->unit());
    }

    /**
     * @param array<string, mixed> $options
     */
    private function hours(Service $service, array $options): float
    {
        // The options are already normalised: a working time of zero or less
        // has been removed and falls back to the time stored with the service.
        if (isset($options['hours'])) {
            return (float) $options['hours'];
        }

        return ((float) $service->hoursPerUnit()) * (float) $options['quantity'];
    }

    private function costSum(DraftLine $line): Money
    {
        return Money::fromEuro($line->labourCost())
            ->plus(Money::fromEuro($line->materialCost()))
            ->plus(Money::fromEuro($line->subcontractorCost()))
            ->plus(Money::fromEuro($line->machineCost()))
            ->plus(Money::fromEuro($line->disposalCost()));
    }

    private function zeroCostComponent(DraftLine $line, string $costType): void
    {
        match ($costType) {
            'Labour' => $line->setLabourCost('0.00'),
            'Subcontractor' => $line->setSubcontractorCost('0.00'),
            'Machine' => $line->setMachineCost('0.00'),
            default => $line->setMaterialCost('0.00'),
        };
    }
}
