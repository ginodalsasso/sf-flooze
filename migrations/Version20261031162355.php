<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * LoanLine: add an optional negotiated monthly payment for bank-smoothed (lissé) lines.
 *
 * When a loan's lines share different durations, the bank routinely smooths the capital +
 * interest instalment of each line so the combined total stays constant — a value that no
 * longer matches the annuity formula on that line's own capital/rate/duration.
 */
final class Version20261031162355 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'LoanLine: add negotiated_monthly_payment for bank-smoothed lines';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE loan_line ADD negotiated_monthly_payment NUMERIC(15, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE loan_line DROP negotiated_monthly_payment');
    }
}
