<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Clears the destination leg from the transactions that are not transfers.
 *
 * The form exposes the destination fields whatever the type is selected, and the service
 * used to store them as posted. Only a transfer credits a destination: on any other type
 * the values are meaningless, and they made the row read as an incoming transfer on an
 * account that never received anything.
 *
 * Irreversible by design — there is nothing meaningful to restore, hence the empty down().
 * Balances are unaffected: they already ignore a destination outside a transfer.
 */
final class Version20260801093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clear the meaningless destination account/amount left on non-transfer transactions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'UPDATE transaction SET destination_account_id = NULL, destination_amount = NULL
             WHERE type <> \'transfer\' AND (destination_account_id IS NOT NULL OR destination_amount IS NOT NULL)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('The cleared destination values carried no information to restore.');
    }
}
