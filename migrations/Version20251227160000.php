<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251227160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Discord OAuth fields to users table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE users ADD discord_id VARCHAR(255) DEFAULT NULL, ADD discord_username VARCHAR(255) DEFAULT NULL, ADD discord_avatar VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_1483A5E943349DE ON users (discord_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_1483A5E943349DE ON users
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users DROP discord_id, DROP discord_username, DROP discord_avatar
        SQL);
    }
}
