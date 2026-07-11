<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260710055526 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create asset_entry table and refactor asset (remove quantity/avg_price, currency as enum)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE asset_entry (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, kind VARCHAR(255) NOT NULL, quantity NUMERIC(18, 8) NOT NULL, unit_price NUMERIC(15, 4) NOT NULL, fx_rate NUMERIC(15, 6) NOT NULL, fees NUMERIC(15, 2) NOT NULL, note VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, asset_id INT NOT NULL, account_id INT DEFAULT NULL, space_id INT NOT NULL, INDEX IDX_BE7AB3375DA1941 (asset_id), INDEX IDX_BE7AB3379B6B5FBA (account_id), INDEX IDX_BE7AB33723575340 (space_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE asset_entry ADD CONSTRAINT FK_BE7AB3375DA1941 FOREIGN KEY (asset_id) REFERENCES asset (id)');
        $this->addSql('ALTER TABLE asset_entry ADD CONSTRAINT FK_BE7AB3379B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE asset_entry ADD CONSTRAINT FK_BE7AB33723575340 FOREIGN KEY (space_id) REFERENCES space (id)');
        $this->addSql('ALTER TABLE asset DROP quantity, DROP avg_price, CHANGE currency currency VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE asset_entry DROP FOREIGN KEY FK_BE7AB3375DA1941');
        $this->addSql('ALTER TABLE asset_entry DROP FOREIGN KEY FK_BE7AB3379B6B5FBA');
        $this->addSql('ALTER TABLE asset_entry DROP FOREIGN KEY FK_BE7AB33723575340');
        $this->addSql('DROP TABLE asset_entry');
        $this->addSql('ALTER TABLE asset ADD quantity NUMERIC(18, 8) NOT NULL, ADD avg_price NUMERIC(15, 4) NOT NULL, CHANGE currency currency VARCHAR(3) NOT NULL');
    }
}
