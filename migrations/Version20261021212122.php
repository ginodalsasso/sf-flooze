<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261021212122 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE loan (id INT AUTO_INCREMENT NOT NULL, bank_name VARCHAR(100) NOT NULL, amount NUMERIC(15, 2) NOT NULL, rate NUMERIC(6, 4) NOT NULL, insurance_rate NUMERIC(6, 4) NOT NULL, start_date DATE NOT NULL, duration_months SMALLINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, property_id INT NOT NULL, space_id INT NOT NULL, INDEX IDX_C5D30D03549213EC (property_id), INDEX IDX_C5D30D0323575340 (space_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE loan ADD CONSTRAINT FK_C5D30D03549213EC FOREIGN KEY (property_id) REFERENCES property (id)');
        $this->addSql('ALTER TABLE loan ADD CONSTRAINT FK_C5D30D0323575340 FOREIGN KEY (space_id) REFERENCES space (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE loan DROP FOREIGN KEY FK_C5D30D03549213EC');
        $this->addSql('ALTER TABLE loan DROP FOREIGN KEY FK_C5D30D0323575340');
        $this->addSql('DROP TABLE loan');
    }
}
