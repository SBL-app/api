<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Statut des inscriptions (BAGA-225) : les inscriptions existantes sont considérées approuvées.
 */
final class Version20261125100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add registration.status';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE registration ADD status VARCHAR(20) DEFAULT 'pending' NOT NULL");
        $this->addSql("UPDATE registration SET status = 'approved'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE registration DROP status');
    }
}
