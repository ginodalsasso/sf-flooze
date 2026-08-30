<?php

declare(strict_types=1);

namespace App\Service\Finance;

use App\Dto\Finance\DueOccurrenceDto;
use App\Dto\Finance\TransactionInputDto;
use App\Entity\RecurringTransaction;
use App\Entity\Space;
use App\Entity\Transaction;
use App\Enum\TransactionTypeEnum;
use App\Repository\Contract\RecurringTransactionRepositoryInterface;
use App\Service\Finance\Contract\RecurrenceScheduleServiceInterface;
use App\Service\Finance\Contract\RecurringTransactionServiceInterface;
use App\Service\Finance\Contract\TransactionServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

final class RecurringTransactionService implements RecurringTransactionServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TransactionServiceInterface $transactionService,
        private readonly RecurrenceScheduleServiceInterface $schedule,
        private readonly RecurringTransactionRepositoryInterface $repository,
        private readonly ClockInterface $clock,
    ) {}

    /** The form is not a security boundary: everything it offered is checked again here. */
    public function save(RecurringTransaction $recurrence): void
    {
        $this->guardStrictlyPositive($recurrence->getAmount());
        $this->guardInSpace($recurrence);
        $this->guardValidTransfer($recurrence);
        $this->guardCategoryAppliesToType($recurrence);
        $this->guardValidPeriod($recurrence);
        $this->realignCursor($recurrence);

        $this->em->persist($recurrence);
        $this->em->flush();
    }

    /** Soft delete only: the Transactions already materialised are facts and outlive the template. */
    public function delete(RecurringTransaction $recurrence): void
    {
        $recurrence->softDelete();
        $this->em->flush();
    }

    public function toggleActive(RecurringTransaction $recurrence): void
    {
        $recurrence->setIsActive(!$recurrence->isActive());
        $this->em->flush();
    }

    /** @return DueOccurrenceDto[] one per recurrence — only its oldest occurrence is actionable */
    public function findDueOccurrences(Space $space): array
    {
        $today = $this->today();
        $occurrences = [];

        foreach ($this->repository->findDue($space, $today) as $recurrence) {
            $dates = $this->schedule->dueOccurrences($recurrence, $today);

            if ($dates === []) {
                continue;
            }

            $occurrences[] = new DueOccurrenceDto($recurrence, $dates[0], count($dates) - 1);
        }

        usort($occurrences, static fn (DueOccurrenceDto $a, DueOccurrenceDto $b) => $a->date <=> $b->date);

        return $occurrences;
    }

    /** Overdue occurrences of the whole space, backlog included — findDueOccurrences() only exposes the actionable ones. */
    public function countDueOccurrences(Space $space): int
    {
        return array_sum(array_map(
            static fn (DueOccurrenceDto $occurrence) => 1 + $occurrence->backlog,
            $this->findDueOccurrences($space),
        ));
    }

    /** @return DueOccurrenceDto[] */
    public function findUpcomingOccurrences(Space $space, int $days = 30, int $limit = 5): array
    {
        $today = $this->today();
        $horizon = $today->modify(sprintf('+%d days', $days));
        $occurrences = [];

        foreach ($this->repository->findDue($space, $horizon) as $recurrence) {
            foreach ($this->schedule->dueOccurrences($recurrence, $horizon) as $date) {
                // Occurrences up to today are overdue and belong to the confirmation banner.
                if ($date > $today) {
                    $occurrences[] = new DueOccurrenceDto($recurrence, $date);
                }
            }
        }

        usort($occurrences, static fn (DueOccurrenceDto $a, DueOccurrenceDto $b) => $a->date <=> $b->date);

        return array_slice($occurrences, 0, $limit);
    }

    public function confirm(RecurringTransaction $recurrence, \DateTimeImmutable $date): Transaction
    {
        $this->guardDueDate($recurrence, $date);

        $input = new TransactionInputDto();
        $input->space = $recurrence->getSpace();
        $input->account = $recurrence->getAccount();
        $input->destinationAccount = $recurrence->getDestinationAccount();
        $input->type = $recurrence->getType();
        $input->amount = $recurrence->getAmount();
        $input->date = $date;
        $input->description = $recurrence->getLabel();
        $input->category = $recurrence->getCategory();
        $input->tags = $recurrence->getTags()->toArray();
        $input->recurringTransaction = $recurrence;

        // Let the exception escape: on insufficient funds the cursor must stay put and the
        // occurrence stay due, so the cursor only ever moves once the write succeeded.
        $transaction = $this->transactionService->save($input);

        $this->advanceCursor($recurrence, $date);
        $this->em->flush();

        return $transaction;
    }

    public function skip(RecurringTransaction $recurrence, \DateTimeImmutable $date): void
    {
        $this->guardDueDate($recurrence, $date);

        $this->advanceCursor($recurrence, $date);
        $this->em->flush();
    }

    /** Midnight today: comparing a DATE column against a timestamp silently misfires. */
    private function today(): \DateTimeImmutable
    {
        return $this->clock->now()->setTime(0, 0);
    }

    private function guardStrictlyPositive(string $amount): void
    {
        if (bccomp($amount, '0', 2) <= 0) {
            throw new \InvalidArgumentException('Le montant d\'une récurrence doit être strictement positif.');
        }
    }

    /**
     * Rejects a recurrence whose account, destination account, category or tag belongs to
     * another space. The form only offers entities of the space, but a posted id proves nothing.
     */
    private function guardInSpace(RecurringTransaction $recurrence): void
    {
        $owners = [
            'compte' => $recurrence->getAccount()->getSpace(),
            'compte destinataire' => $recurrence->getDestinationAccount()?->getSpace(),
            'catégorie' => $recurrence->getCategory()?->getSpace(),
        ];

        foreach ($recurrence->getTags() as $tag) {
            $owners['tag « ' . $tag->getName() . ' »'] = $tag->getSpace();
        }

        foreach ($owners as $label => $owner) {
            if ($owner !== null && $owner->getId() !== $recurrence->getSpace()->getId()) {
                throw new \InvalidArgumentException(sprintf('Élément hors de cet espace : %s.', $label));
            }
        }
    }

    /** Only a transfer has a destination: a value posted with another type is discarded, not rejected. */
    private function guardValidTransfer(RecurringTransaction $recurrence): void
    {
        if ($recurrence->getType() !== TransactionTypeEnum::TRANSFER) {
            $recurrence->setDestinationAccount(null);

            return;
        }

        $destination = $recurrence->getDestinationAccount();

        if ($destination === null) {
            throw new \InvalidArgumentException('Un virement récurrent doit désigner un compte destinataire.');
        }

        if ($recurrence->getAccount()->getId() === $destination->getId()) {
            throw new \InvalidArgumentException('Le compte destinataire doit être différent du compte source.');
        }
    }

    /**
     * Transactions check this in the form; a recurrence cannot, since it would produce 12
     * inconsistent transactions a year without ever passing through a form again.
     */
    private function guardCategoryAppliesToType(RecurringTransaction $recurrence): void
    {
        $category = $recurrence->getCategory();

        if ($category !== null && !$category->appliesTo($recurrence->getType())) {
            throw new \InvalidArgumentException(sprintf(
                'La catégorie « %s » ne s\'applique pas à une opération de type %s.',
                $category->getName(),
                $recurrence->getType()->label(),
            ));
        }
    }

    private function guardValidPeriod(RecurringTransaction $recurrence): void
    {
        if ($recurrence->getIntervalCount() < 1) {
            throw new \InvalidArgumentException('L\'intervalle de répétition doit valoir au moins 1.');
        }

        $endDate = $recurrence->getEndDate();

        if ($endDate !== null && $endDate < $recurrence->getStartDate()) {
            throw new \InvalidArgumentException('La date de fin ne peut pas précéder la date de début.');
        }
    }

    /**
     * On creation the cursor starts on startDate; on edit max() keeps it ahead so confirmed
     * occurrences are never replayed. The -1 day makes next() return the first occurrence on or
     * after the cursor, which re-snaps it when a new startDate shifted the whole schedule.
     */
    private function realignCursor(RecurringTransaction $recurrence): void
    {
        $cursor = $recurrence->getId() === null
            ? $recurrence->getStartDate()
            : max($recurrence->getStartDate(), $recurrence->getNextOccurrenceDate());

        $recurrence->setNextOccurrenceDate(
            $this->schedule->next($recurrence, $cursor->modify('-1 day')) ?? $cursor,
        );
    }

    /**
     * Only the oldest due occurrence may be treated. The cursor is a single date: moving it to a
     * later occurrence would drop every earlier one without a transaction and without a decision.
     * The date also comes from a POST, so it is forgeable and has to be checked either way.
     */
    private function guardDueDate(RecurringTransaction $recurrence, \DateTimeImmutable $date): void
    {
        $oldest = $this->schedule->dueOccurrences($recurrence, $this->today(), 1);

        // == compares the instant; === would require the very same instance and never match.
        if ($oldest === [] || $oldest[0] != $date->setTime(0, 0)) {
            throw new \InvalidArgumentException(
                'Cette échéance ne peut pas être traitée : il faut d\'abord régler la plus ancienne.',
            );
        }
    }

    private function advanceCursor(RecurringTransaction $recurrence, \DateTimeImmutable $date): void
    {
        $next = $this->schedule->next($recurrence, $date);

        if ($next === null) {
            $recurrence->setIsActive(false);

            return;
        }

        $recurrence->setNextOccurrenceDate($next);
    }
}
