<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Flux d'inscription des équipes à une saison (issue api#35) :
 * - registration : statut + dates de création/revue
 * - season       : fenêtre d'inscription (dates d'ouverture/fermeture)
 */
final class Version20260723120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add registration status/dates and season registration window';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE registration ADD status VARCHAR(20) NOT NULL DEFAULT 'pending'");
        $this->addSql('ALTER TABLE registration ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE registration ADD reviewed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');

        $this->addSql('ALTER TABLE season ADD registration_open_date DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE season ADD registration_close_date DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE registration DROP status');
        $this->addSql('ALTER TABLE registration DROP created_at');
        $this->addSql('ALTER TABLE registration DROP reviewed_at');

        $this->addSql('ALTER TABLE season DROP registration_open_date');
        $this->addSql('ALTER TABLE season DROP registration_close_date');
    }
}
