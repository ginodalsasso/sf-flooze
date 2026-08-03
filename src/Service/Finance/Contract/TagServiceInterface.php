<?php

declare(strict_types=1);

namespace App\Service\Finance\Contract;

use App\Entity\Tag;

/** Tag persistence. */
interface TagServiceInterface
{
    public function save(Tag $tag): void;

    /** The pivot rows go with it: transaction_tag cascades on delete. */
    public function delete(Tag $tag): void;
}
