<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260725000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add soft-delete (deleted_at) to asset_entry';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE asset_entry ADD deleted_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE asset_entry DROP deleted_at');
    }
}
