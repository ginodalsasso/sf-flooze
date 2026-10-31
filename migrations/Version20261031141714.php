<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261031141714 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rent_payment DROP INDEX IDX_8C04FF7D2FC0CB0F, ADD UNIQUE INDEX UNIQ_8C04FF7D2FC0CB0F (transaction_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rent_payment DROP INDEX UNIQ_8C04FF7D2FC0CB0F, ADD INDEX IDX_8C04FF7D2FC0CB0F (transaction_id)');
    }
}
