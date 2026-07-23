<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260206100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create team_member table for team membership management';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE team_member (
                id SERIAL PRIMARY KEY,
                team_id INT NOT NULL,
                user_id INT NOT NULL,
                role VARCHAR(20) NOT NULL DEFAULT 'member',
                joined_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT fk_team_member_team FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE,
                CONSTRAINT fk_team_member_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                CONSTRAINT unique_team_user UNIQUE (team_id, user_id)
            )
        SQL);

        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN team_member.joined_at IS '(DC2Type:datetime_immutable)'
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DROP TABLE team_member
        SQL);
    }
}
