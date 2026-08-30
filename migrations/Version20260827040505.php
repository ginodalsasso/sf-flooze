<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260827040505 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE recurring_transaction (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(255) NOT NULL, amount NUMERIC(15, 2) NOT NULL, label VARCHAR(255) NOT NULL, frequency VARCHAR(255) NOT NULL, interval_count SMALLINT DEFAULT 1 NOT NULL, start_date DATE NOT NULL, end_date DATE DEFAULT NULL, next_occurrence_date DATE NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, account_id INT NOT NULL, destination_account_id INT DEFAULT NULL, category_id INT DEFAULT NULL, space_id INT NOT NULL, INDEX IDX_D3509AA69B6B5FBA (account_id), INDEX IDX_D3509AA6C652C408 (destination_account_id), INDEX IDX_D3509AA612469DE2 (category_id), INDEX IDX_D3509AA623575340 (space_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE recurring_transaction_tag (recurring_transaction_id INT NOT NULL, tag_id INT NOT NULL, INDEX IDX_73070BBC5B71D755 (recurring_transaction_id), INDEX IDX_73070BBCBAD26311 (tag_id), PRIMARY KEY (recurring_transaction_id, tag_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE recurring_transaction ADD CONSTRAINT FK_D3509AA69B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE recurring_transaction ADD CONSTRAINT FK_D3509AA6C652C408 FOREIGN KEY (destination_account_id) REFERENCES account (id)');
        $this->addSql('ALTER TABLE recurring_transaction ADD CONSTRAINT FK_D3509AA612469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE recurring_transaction ADD CONSTRAINT FK_D3509AA623575340 FOREIGN KEY (space_id) REFERENCES space (id)');
        $this->addSql('ALTER TABLE recurring_transaction_tag ADD CONSTRAINT FK_73070BBC5B71D755 FOREIGN KEY (recurring_transaction_id) REFERENCES recurring_transaction (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recurring_transaction_tag ADD CONSTRAINT FK_73070BBCBAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE transaction ADD recurring_transaction_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D15B71D755 FOREIGN KEY (recurring_transaction_id) REFERENCES recurring_transaction (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_723705D15B71D755 ON transaction (recurring_transaction_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE recurring_transaction DROP FOREIGN KEY FK_D3509AA69B6B5FBA');
        $this->addSql('ALTER TABLE recurring_transaction DROP FOREIGN KEY FK_D3509AA6C652C408');
        $this->addSql('ALTER TABLE recurring_transaction DROP FOREIGN KEY FK_D3509AA612469DE2');
        $this->addSql('ALTER TABLE recurring_transaction DROP FOREIGN KEY FK_D3509AA623575340');
        $this->addSql('ALTER TABLE recurring_transaction_tag DROP FOREIGN KEY FK_73070BBC5B71D755');
        $this->addSql('ALTER TABLE recurring_transaction_tag DROP FOREIGN KEY FK_73070BBCBAD26311');
        $this->addSql('DROP TABLE recurring_transaction');
        $this->addSql('DROP TABLE recurring_transaction_tag');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D15B71D755');
        $this->addSql('DROP INDEX IDX_723705D15B71D755 ON transaction');
        $this->addSql('ALTER TABLE transaction DROP recurring_transaction_id');
    }
}
