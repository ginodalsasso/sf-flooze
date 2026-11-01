<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261101160709 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE tax_item (id INT AUTO_INCREMENT NOT NULL, fiscal_bucket VARCHAR(255) NOT NULL, fiscal_role VARCHAR(255) NOT NULL, done TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, tax_year_id INT NOT NULL, space_id INT NOT NULL, INDEX IDX_3942AE61895EAD (tax_year_id), INDEX IDX_3942AE623575340 (space_id), UNIQUE INDEX uniq_tax_item_year_bucket_role (tax_year_id, fiscal_bucket, fiscal_role), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE tax_item ADD CONSTRAINT FK_3942AE61895EAD FOREIGN KEY (tax_year_id) REFERENCES tax_year (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tax_item ADD CONSTRAINT FK_3942AE623575340 FOREIGN KEY (space_id) REFERENCES space (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tax_item DROP FOREIGN KEY FK_3942AE61895EAD');
        $this->addSql('ALTER TABLE tax_item DROP FOREIGN KEY FK_3942AE623575340');
        $this->addSql('DROP TABLE tax_item');
    }
}
