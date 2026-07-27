<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260727084124 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Multi-currency: reference currency on space, frozen fx rate and credited amount on transaction.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE asset_entry CHANGE deleted_at deleted_at DATETIME DEFAULT NULL');

        // Existing spaces and transactions were implicitly in EUR at a rate of 1.
        $this->addSql('ALTER TABLE space ADD currency VARCHAR(255) DEFAULT \'EUR\' NOT NULL');
        $this->addSql('ALTER TABLE transaction ADD fx_rate NUMERIC(15, 6) DEFAULT \'1.000000\' NOT NULL, ADD destination_amount NUMERIC(15, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE asset_entry CHANGE deleted_at deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE space DROP currency');
        $this->addSql('ALTER TABLE transaction DROP fx_rate, DROP destination_amount');
    }
}
