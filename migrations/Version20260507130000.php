<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260507130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create game_result table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE game_result (
                id SERIAL PRIMARY KEY,
                game_id INT NOT NULL,
                submitted_by_team_id INT NOT NULL,
                submitted_by_id INT NOT NULL,
                score1 INT NOT NULL,
                score2 INT NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'pending_validation',
                responded_by_id INT DEFAULT NULL,
                responded_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT FK_game_result_game FOREIGN KEY (game_id) REFERENCES game (id) ON DELETE CASCADE,
                CONSTRAINT FK_game_result_team FOREIGN KEY (submitted_by_team_id) REFERENCES team (id) ON DELETE CASCADE,
                CONSTRAINT FK_game_result_submitted_by FOREIGN KEY (submitted_by_id) REFERENCES "user" (id) ON DELETE CASCADE,
                CONSTRAINT FK_game_result_responded_by FOREIGN KEY (responded_by_id) REFERENCES "user" (id) ON DELETE SET NULL
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_game_result_game ON game_result (game_id)');
        $this->addSql('CREATE INDEX IDX_game_result_team ON game_result (submitted_by_team_id)');
        $this->addSql('CREATE INDEX IDX_game_result_submitted_by ON game_result (submitted_by_id)');
        $this->addSql('CREATE INDEX IDX_game_result_responded_by ON game_result (responded_by_id)');
        $this->addSql('COMMENT ON COLUMN game_result.responded_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN game_result.created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE game_result');
    }
}
