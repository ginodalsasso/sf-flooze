<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261031141600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE rent_payment (id INT AUTO_INCREMENT NOT NULL, amount NUMERIC(15, 2) NOT NULL, due_date DATE NOT NULL, paid_date DATE DEFAULT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, lease_id INT NOT NULL, transaction_id INT DEFAULT NULL, space_id INT NOT NULL, INDEX IDX_8C04FF7DD3CA542C (lease_id), INDEX IDX_8C04FF7D2FC0CB0F (transaction_id), INDEX IDX_8C04FF7D23575340 (space_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE rent_payment ADD CONSTRAINT FK_8C04FF7DD3CA542C FOREIGN KEY (lease_id) REFERENCES `lease` (id)');
        $this->addSql('ALTER TABLE rent_payment ADD CONSTRAINT FK_8C04FF7D2FC0CB0F FOREIGN KEY (transaction_id) REFERENCES transaction (id)');
        $this->addSql('ALTER TABLE rent_payment ADD CONSTRAINT FK_8C04FF7D23575340 FOREIGN KEY (space_id) REFERENCES space (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rent_payment DROP FOREIGN KEY FK_8C04FF7DD3CA542C');
        $this->addSql('ALTER TABLE rent_payment DROP FOREIGN KEY FK_8C04FF7D2FC0CB0F');
        $this->addSql('ALTER TABLE rent_payment DROP FOREIGN KEY FK_8C04FF7D23575340');
        $this->addSql('DROP TABLE rent_payment');
    }
}
