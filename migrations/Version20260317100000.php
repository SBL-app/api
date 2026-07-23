<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260317100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create match_result table and seed pending_result/contested game statuses';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE match_result (
                id SERIAL PRIMARY KEY,
                game_id INT NOT NULL,
                submitted_by_id INT NOT NULL,
                team_id INT NOT NULL,
                score1 INT NOT NULL,
                score2 INT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                contest_reason TEXT DEFAULT NULL,
                validated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT FK_match_result_game FOREIGN KEY (game_id) REFERENCES game (id) ON DELETE CASCADE,
                CONSTRAINT FK_match_result_user FOREIGN KEY (submitted_by_id) REFERENCES "user" (id) ON DELETE CASCADE,
                CONSTRAINT FK_match_result_team FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_match_result_game_id ON match_result (game_id)');
        $this->addSql('CREATE INDEX IDX_match_result_team_id ON match_result (team_id)');
        $this->addSql('CREATE INDEX IDX_match_result_submitted_by_id ON match_result (submitted_by_id)');
        $this->addSql('COMMENT ON COLUMN match_result.validated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN match_result.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql("INSERT INTO game_status (name) SELECT 'pending_result' WHERE NOT EXISTS (SELECT 1 FROM game_status WHERE name = 'pending_result')");
        $this->addSql("INSERT INTO game_status (name) SELECT 'contested' WHERE NOT EXISTS (SELECT 1 FROM game_status WHERE name = 'contested')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE match_result');
        $this->addSql("DELETE FROM game_status WHERE name IN ('pending_result', 'contested')");
    }
}
