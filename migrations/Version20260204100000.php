<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260204100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add API key expiration and token invalidation fields to users table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE users ADD api_key_expires_at TIMESTAMP NULL DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users ADD token_invalidated_at TIMESTAMP NULL DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE users DROP COLUMN api_key_expires_at
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users DROP COLUMN token_invalidated_at
        SQL);
    }
}
