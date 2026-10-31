<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261031152950 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE loan_payment (id INT AUTO_INCREMENT NOT NULL, month_number INT NOT NULL, due_date DATE NOT NULL, capital_part NUMERIC(15, 2) NOT NULL, interest_part NUMERIC(15, 2) NOT NULL, insurance_part NUMERIC(15, 2) NOT NULL, remaining_capital NUMERIC(15, 2) NOT NULL, paid_date DATE NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, loan_id INT NOT NULL, transaction_id INT DEFAULT NULL, space_id INT NOT NULL, INDEX IDX_43670A79CE73868F (loan_id), INDEX IDX_43670A792FC0CB0F (transaction_id), INDEX IDX_43670A7923575340 (space_id), UNIQUE INDEX uniq_loan_payment_loan_month (loan_id, month_number), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE loan_payment ADD CONSTRAINT FK_43670A79CE73868F FOREIGN KEY (loan_id) REFERENCES loan (id)');
        $this->addSql('ALTER TABLE loan_payment ADD CONSTRAINT FK_43670A792FC0CB0F FOREIGN KEY (transaction_id) REFERENCES transaction (id)');
        $this->addSql('ALTER TABLE loan_payment ADD CONSTRAINT FK_43670A7923575340 FOREIGN KEY (space_id) REFERENCES space (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE loan_payment DROP FOREIGN KEY FK_43670A79CE73868F');
        $this->addSql('ALTER TABLE loan_payment DROP FOREIGN KEY FK_43670A792FC0CB0F');
        $this->addSql('ALTER TABLE loan_payment DROP FOREIGN KEY FK_43670A7923575340');
        $this->addSql('DROP TABLE loan_payment');
    }
}
