<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Table clé/valeur de la configuration du bot (BAGA-68).
 */
final class Version20261123100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create bot_setting table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE bot_setting (setting_key VARCHAR(64) NOT NULL, value TEXT DEFAULT NULL, PRIMARY KEY(setting_key))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE bot_setting');
    }
}
