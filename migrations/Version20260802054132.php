<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds cross-cutting tags on transactions.
 *
 * A tag groups transactions around a project or an event whatever their category:
 * it carries no fiscal meaning. See ARCHITECTURE.md → "Tag ≠ Category".
 *
 * The pivot cascades on both sides so deleting a tag detaches it without leaving
 * orphan rows — the transactions themselves are untouched.
 */
final class Version20260802054132 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tag and transaction_tag: cross-cutting labels on transactions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tag (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, space_id INT NOT NULL, INDEX IDX_389B78323575340 (space_id), UNIQUE INDEX uniq_tag_space_name (space_id, name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE transaction_tag (transaction_id INT NOT NULL, tag_id INT NOT NULL, INDEX IDX_F8CD024A2FC0CB0F (transaction_id), INDEX IDX_F8CD024ABAD26311 (tag_id), PRIMARY KEY (transaction_id, tag_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE tag ADD CONSTRAINT FK_389B78323575340 FOREIGN KEY (space_id) REFERENCES space (id)');
        $this->addSql('ALTER TABLE transaction_tag ADD CONSTRAINT FK_F8CD024A2FC0CB0F FOREIGN KEY (transaction_id) REFERENCES transaction (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE transaction_tag ADD CONSTRAINT FK_F8CD024ABAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tag DROP FOREIGN KEY FK_389B78323575340');
        $this->addSql('ALTER TABLE transaction_tag DROP FOREIGN KEY FK_F8CD024A2FC0CB0F');
        $this->addSql('ALTER TABLE transaction_tag DROP FOREIGN KEY FK_F8CD024ABAD26311');
        $this->addSql('DROP TABLE tag');
        $this->addSql('DROP TABLE transaction_tag');
    }
}
