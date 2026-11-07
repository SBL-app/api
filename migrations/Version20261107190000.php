<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add users.email and users.notification_channels (default: discord only)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD email VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD notification_channels JSON NOT NULL DEFAULT \'["discord"]\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP notification_channels');
        $this->addSql('ALTER TABLE users DROP email');
    }
}
