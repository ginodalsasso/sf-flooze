<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260710053029 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change account.currency to enum-backed VARCHAR for CurrencyEnum';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE account CHANGE currency currency VARCHAR(255) NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE account CHANGE currency currency VARCHAR(3) NOT NULL");
    }
}
