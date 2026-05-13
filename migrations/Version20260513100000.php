<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260513100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add season.is_finalized for automated season closure';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE season ADD COLUMN is_finalized BOOLEAN NOT NULL DEFAULT false
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE season DROP COLUMN is_finalized
        SQL);
    }
}
