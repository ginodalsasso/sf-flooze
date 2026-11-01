<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261101155301 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE tax_year (id INT AUTO_INCREMENT NOT NULL, year SMALLINT NOT NULL, status VARCHAR(255) NOT NULL, regime_choices JSON DEFAULT NULL, note LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, space_id INT NOT NULL, INDEX IDX_A70D7CCF23575340 (space_id), UNIQUE INDEX uniq_tax_year_space_year (space_id, year), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE tax_year ADD CONSTRAINT FK_A70D7CCF23575340 FOREIGN KEY (space_id) REFERENCES space (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tax_year DROP FOREIGN KEY FK_A70D7CCF23575340');
        $this->addSql('DROP TABLE tax_year');
    }
}
