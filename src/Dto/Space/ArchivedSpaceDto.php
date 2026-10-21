<?php

declare(strict_types=1);

namespace App\Dto\Space;

use App\Entity\Space;

/** An archived space and the state of its restore window, computed for the view. */
final readonly class ArchivedSpaceDto
{
    public function __construct(
        public Space $space,
        public \DateTimeImmutable $restorableUntil,
        public bool $isRestorable,
        public bool $isTypeTaken,
    ) {}
}
