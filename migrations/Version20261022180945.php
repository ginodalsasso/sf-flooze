<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Category: the two fiscal booleans become a fiscal bucket + a role.
 *
 * A boolean could say *whether* a category had fiscal meaning, never *in which catégorie de
 * revenus* — so the declaration had no way to keep charges inside their own category.
 *
 * The old flags carried the role, so it is preserved. The bucket cannot be guessed from them
 * and is left NULL: a category with a role but no bucket counts nowhere, and the UI flags it
 * as "catégorie fiscale à préciser".
 */
final class Version20261022180945 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Category: fiscal_bucket + fiscal_role replace is_declarable / is_deductible';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category ADD fiscal_bucket VARCHAR(255) DEFAULT NULL, ADD fiscal_role VARCHAR(255) DEFAULT NULL');

        // Declarable wins when both flags were set: an income category is the likelier intent.
        $this->addSql("UPDATE category SET fiscal_role = 'revenu' WHERE is_declarable = 1");
        $this->addSql("UPDATE category SET fiscal_role = 'charge' WHERE is_deductible = 1 AND is_declarable = 0");

        $this->addSql('ALTER TABLE category DROP is_deductible, DROP is_declarable');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category ADD is_deductible TINYINT NOT NULL DEFAULT 0, ADD is_declarable TINYINT NOT NULL DEFAULT 0');

        $this->addSql("UPDATE category SET is_declarable = 1 WHERE fiscal_role = 'revenu'");
        $this->addSql("UPDATE category SET is_deductible = 1 WHERE fiscal_role = 'charge'");

        $this->addSql('ALTER TABLE category DROP fiscal_bucket, DROP fiscal_role');
    }
}
