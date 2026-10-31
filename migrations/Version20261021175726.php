<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261021175726 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE `lease` (id INT AUTO_INCREMENT NOT NULL, rent NUMERIC(15, 2) NOT NULL, charges NUMERIC(15, 2) NOT NULL, type VARCHAR(255) NOT NULL, security_deposit NUMERIC(15, 2) NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, property_id INT NOT NULL, space_id INT NOT NULL, INDEX IDX_E6C77495549213EC (property_id), INDEX IDX_E6C7749523575340 (space_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE lease_tenant (lease_id INT NOT NULL, tenant_id INT NOT NULL, INDEX IDX_32081F82D3CA542C (lease_id), INDEX IDX_32081F829033212A (tenant_id), PRIMARY KEY (lease_id, tenant_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE property (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, address VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, purchase_price NUMERIC(15, 2) NOT NULL, purchase_date DATE NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, space_id INT NOT NULL, INDEX IDX_8BF21CDE23575340 (space_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE tenant (id INT AUTO_INCREMENT NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, phone VARCHAR(30) DEFAULT NULL, monthly_income NUMERIC(15, 2) NOT NULL, guarantor_name VARCHAR(200) DEFAULT NULL, guarantor_income NUMERIC(15, 2) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, space_id INT NOT NULL, INDEX IDX_4E59C46223575340 (space_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE `lease` ADD CONSTRAINT FK_E6C77495549213EC FOREIGN KEY (property_id) REFERENCES property (id)');
        $this->addSql('ALTER TABLE `lease` ADD CONSTRAINT FK_E6C7749523575340 FOREIGN KEY (space_id) REFERENCES space (id)');
        $this->addSql('ALTER TABLE lease_tenant ADD CONSTRAINT FK_32081F82D3CA542C FOREIGN KEY (lease_id) REFERENCES `lease` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lease_tenant ADD CONSTRAINT FK_32081F829033212A FOREIGN KEY (tenant_id) REFERENCES tenant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE property ADD CONSTRAINT FK_8BF21CDE23575340 FOREIGN KEY (space_id) REFERENCES space (id)');
        $this->addSql('ALTER TABLE tenant ADD CONSTRAINT FK_4E59C46223575340 FOREIGN KEY (space_id) REFERENCES space (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `lease` DROP FOREIGN KEY FK_E6C77495549213EC');
        $this->addSql('ALTER TABLE `lease` DROP FOREIGN KEY FK_E6C7749523575340');
        $this->addSql('ALTER TABLE lease_tenant DROP FOREIGN KEY FK_32081F82D3CA542C');
        $this->addSql('ALTER TABLE lease_tenant DROP FOREIGN KEY FK_32081F829033212A');
        $this->addSql('ALTER TABLE property DROP FOREIGN KEY FK_8BF21CDE23575340');
        $this->addSql('ALTER TABLE tenant DROP FOREIGN KEY FK_4E59C46223575340');
        $this->addSql('DROP TABLE `lease`');
        $this->addSql('DROP TABLE lease_tenant');
        $this->addSql('DROP TABLE property');
        $this->addSql('DROP TABLE tenant');
    }
}
