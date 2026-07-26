<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260726000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add applicable_types (income/expense/transfer scope) to category';
    }

    public function up(Schema $schema): void
    {
        // Added nullable then filled: MySQL cannot backfill a NOT NULL JSON column without default.
        $this->addSql('ALTER TABLE category ADD applicable_types JSON DEFAULT NULL');
        $this->addSql("UPDATE category SET applicable_types = '[]'");
        $this->addSql('ALTER TABLE category MODIFY applicable_types JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category DROP applicable_types');
    }
}
