<?php

declare(strict_types=1);

namespace App\Service\Finance;

use App\Entity\Account;
use Doctrine\ORM\EntityManagerInterface;

class AccountService
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function save(Account $account): void
    {
        $this->em->persist($account);
        $this->em->flush();
    }

    public function delete(Account $account): void
    {
        $account->softDelete();
        $this->em->flush();
    }
}
