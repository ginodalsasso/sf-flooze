<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260711011923 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE asset_entry ADD funding_account_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE asset_entry ADD CONSTRAINT FK_BE7AB337D6C4FFE5 FOREIGN KEY (funding_account_id) REFERENCES account (id)');
        $this->addSql('CREATE INDEX IDX_BE7AB337D6C4FFE5 ON asset_entry (funding_account_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE asset_entry DROP FOREIGN KEY FK_BE7AB337D6C4FFE5');
        $this->addSql('DROP INDEX IDX_BE7AB337D6C4FFE5 ON asset_entry');
        $this->addSql('ALTER TABLE asset_entry DROP funding_account_id');
    }
}
