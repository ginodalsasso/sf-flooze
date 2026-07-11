<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260711035129 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add asset_entry_id foreign key to transaction table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE transaction ADD asset_entry_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1385D53C2 FOREIGN KEY (asset_entry_id) REFERENCES asset_entry (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_723705D1385D53C2 ON transaction (asset_entry_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D1385D53C2');
        $this->addSql('DROP INDEX IDX_723705D1385D53C2 ON transaction');
        $this->addSql('ALTER TABLE transaction DROP asset_entry_id');
    }
}
