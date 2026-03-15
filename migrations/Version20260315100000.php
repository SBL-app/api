<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260315100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create match_report table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE match_report (
                id SERIAL PRIMARY KEY,
                game_id INT NOT NULL,
                team_id INT NOT NULL,
                requested_by_id INT NOT NULL,
                reason TEXT DEFAULT NULL,
                is_admin_forced BOOLEAN NOT NULL DEFAULT FALSE,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT FK_match_report_game FOREIGN KEY (game_id) REFERENCES game (id) ON DELETE CASCADE,
                CONSTRAINT FK_match_report_team FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE,
                CONSTRAINT FK_match_report_user FOREIGN KEY (requested_by_id) REFERENCES "user" (id) ON DELETE CASCADE
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_match_report_game ON match_report (game_id)');
        $this->addSql('CREATE INDEX IDX_match_report_team ON match_report (team_id)');
        $this->addSql('COMMENT ON COLUMN match_report.created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE match_report');
    }
}
