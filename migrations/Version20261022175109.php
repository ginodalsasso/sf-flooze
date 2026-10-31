<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261022175109 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE loan_line (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(100) NOT NULL, amount NUMERIC(15, 2) NOT NULL, rate NUMERIC(6, 4) NOT NULL, insurance_rate NUMERIC(6, 4) NOT NULL, duration_months SMALLINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, loan_id INT NOT NULL, space_id INT NOT NULL, INDEX IDX_B9CE82CE73868F (loan_id), INDEX IDX_B9CE8223575340 (space_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE loan_line ADD CONSTRAINT FK_B9CE82CE73868F FOREIGN KEY (loan_id) REFERENCES loan (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE loan_line ADD CONSTRAINT FK_B9CE8223575340 FOREIGN KEY (space_id) REFERENCES space (id)');
        $this->addSql('ALTER TABLE loan DROP amount, DROP rate, DROP insurance_rate, DROP duration_months');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE loan_line DROP FOREIGN KEY FK_B9CE82CE73868F');
        $this->addSql('ALTER TABLE loan_line DROP FOREIGN KEY FK_B9CE8223575340');
        $this->addSql('DROP TABLE loan_line');
        $this->addSql('ALTER TABLE loan ADD amount NUMERIC(15, 2) NOT NULL, ADD rate NUMERIC(6, 4) NOT NULL, ADD insurance_rate NUMERIC(6, 4) NOT NULL, ADD duration_months SMALLINT NOT NULL');
    }
}
