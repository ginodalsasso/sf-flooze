<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * account.balance (stored, incrementally maintained) becomes account.opening_balance.
 *
 * The stored column held opening balance + every movement. Renaming it is not enough: the
 * movements must be subtracted back out, otherwise they would be counted twice once the
 * current balance is derived from the transactions.
 */
final class Version20260801090000 extends AbstractMigration
{
    /**
     * Net movement of the active transactions of an account, in its own currency.
     *
     * Only a transfer credits its destination: income and expense rows may carry a stale
     * destination_account_id, and counting it would inflate the account twice over.
     */
    private const MOVEMENTS = <<<'SQL'
        COALESCE((
            SELECT SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE -t.amount END)
            FROM transaction t
            WHERE t.account_id = a.id AND t.deleted_at IS NULL
        ), 0)
        + COALESCE((
            SELECT SUM(COALESCE(t.destination_amount, t.amount))
            FROM transaction t
            WHERE t.destination_account_id = a.id AND t.account_id <> a.id
              AND t.type = 'transfer' AND t.deleted_at IS NULL
        ), 0)
        SQL;

    public function getDescription(): string
    {
        return 'Account balance is derived from transactions: the stored column becomes opening_balance.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account CHANGE balance opening_balance NUMERIC(15, 2) NOT NULL');
        $this->addSql('UPDATE account a SET a.opening_balance = a.opening_balance - (' . self::MOVEMENTS . ')');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE account a SET a.opening_balance = a.opening_balance + (' . self::MOVEMENTS . ')');
        $this->addSql('ALTER TABLE account CHANGE opening_balance balance NUMERIC(15, 2) NOT NULL');
    }
}
