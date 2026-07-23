<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Table des transferts de joueurs entre équipes (issue app#28).
 */
final class Version20260723140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create transfer table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE transfer (
                id SERIAL PRIMARY KEY,
                player_id INT NOT NULL,
                from_team_id INT DEFAULT NULL,
                to_team_id INT NOT NULL,
                season_id INT NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT FK_transfer_player FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE,
                CONSTRAINT FK_transfer_from_team FOREIGN KEY (from_team_id) REFERENCES team (id) ON DELETE SET NULL,
                CONSTRAINT FK_transfer_to_team FOREIGN KEY (to_team_id) REFERENCES team (id) ON DELETE CASCADE,
                CONSTRAINT FK_transfer_season FOREIGN KEY (season_id) REFERENCES season (id) ON DELETE CASCADE
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_transfer_player ON transfer (player_id)');
        $this->addSql('CREATE INDEX IDX_transfer_from_team ON transfer (from_team_id)');
        $this->addSql('CREATE INDEX IDX_transfer_to_team ON transfer (to_team_id)');
        $this->addSql('CREATE INDEX IDX_transfer_season ON transfer (season_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE transfer');
    }
}
