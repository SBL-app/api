<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Hiérarchie des divisions pour la promotion/relégation (issue api#36) :
 * niveau + nombres de montées/descentes par division.
 */
final class Version20260723130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add division hierarchy (level, promotion_count, relegation_count)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE division ADD level INT NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE division ADD promotion_count INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE division ADD relegation_count INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE division DROP level');
        $this->addSql('ALTER TABLE division DROP promotion_count');
        $this->addSql('ALTER TABLE division DROP relegation_count');
    }
}
