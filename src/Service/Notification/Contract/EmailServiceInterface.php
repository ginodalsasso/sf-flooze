<?php

declare(strict_types=1);

namespace App\Service\Notification\Contract;

use App\Entity\User;

/** Transactional emails sent to users. */
interface EmailServiceInterface
{
    public function sendVerificationEmail(User $user): void;
}
