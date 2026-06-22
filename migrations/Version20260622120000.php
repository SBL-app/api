<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add playoff brackets: bracket and bracket_entry tables, extend game with bracket/round fields, make division_id and week nullable on game';
    }

    public function up(Schema $schema): void
    {
        // Make division_id and week nullable on game (playoff games may not belong to a division)
        $this->addSql('ALTER TABLE game ALTER division_id DROP NOT NULL');
        $this->addSql('ALTER TABLE game ALTER week DROP NOT NULL');

        // Add bracket-related columns to game
        $this->addSql(<<<'SQL'
            ALTER TABLE game
                ADD bracket_id INT DEFAULT NULL,
                ADD round INT DEFAULT NULL,
                ADD bracket_position INT DEFAULT NULL,
                ADD is_third_place_match BOOLEAN DEFAULT false NOT NULL,
                ADD winner_to_game_id INT DEFAULT NULL,
                ADD winner_to_slot INT DEFAULT NULL,
                ADD loser_to_game_id INT DEFAULT NULL,
                ADD loser_to_slot INT DEFAULT NULL
        SQL);

        // Create bracket table
        $this->addSql(<<<'SQL'
            CREATE TABLE bracket (
                id SERIAL PRIMARY KEY,
                division_id INT DEFAULT NULL,
                name VARCHAR(255) NOT NULL,
                format VARCHAR(50) DEFAULT 'single_elimination' NOT NULL,
                has_third_place_match BOOLEAN DEFAULT false NOT NULL,
                qualified_count INT NOT NULL,
                status VARCHAR(20) DEFAULT 'draft' NOT NULL,
                CONSTRAINT FK_BRACKET_DIVISION FOREIGN KEY (division_id) REFERENCES division (id) ON DELETE NO ACTION
            )
        SQL);

        // Create bracket_entry table
        $this->addSql(<<<'SQL'
            CREATE TABLE bracket_entry (
                id SERIAL PRIMARY KEY,
                bracket_id INT NOT NULL,
                seed INT NOT NULL,
                team_id INT NOT NULL,
                CONSTRAINT FK_BRACKET_ENTRY_BRACKET FOREIGN KEY (bracket_id) REFERENCES bracket (id) ON DELETE CASCADE,
                CONSTRAINT FK_BRACKET_ENTRY_TEAM FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE NO ACTION
            )
        SQL);

        // Unique index on bracket_entry (bracket_id, seed)
        $this->addSql('CREATE UNIQUE INDEX unique_bracket_seed ON bracket_entry (bracket_id, seed)');

        // Indexes for FK columns on game
        $this->addSql('CREATE INDEX IDX_game_bracket_id ON game (bracket_id)');
        $this->addSql('CREATE INDEX IDX_game_winner_to_game_id ON game (winner_to_game_id)');
        $this->addSql('CREATE INDEX IDX_game_loser_to_game_id ON game (loser_to_game_id)');

        // Indexes for FK columns on bracket and bracket_entry
        $this->addSql('CREATE INDEX IDX_bracket_division_id ON bracket (division_id)');
        $this->addSql('CREATE INDEX IDX_bracket_entry_bracket_id ON bracket_entry (bracket_id)');
        $this->addSql('CREATE INDEX IDX_bracket_entry_team_id ON bracket_entry (team_id)');

        // Foreign keys on game (added after bracket table exists)
        $this->addSql('ALTER TABLE game ADD CONSTRAINT FK_GAME_BRACKET FOREIGN KEY (bracket_id) REFERENCES bracket (id) ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE game ADD CONSTRAINT FK_GAME_WINNER_TO_GAME FOREIGN KEY (winner_to_game_id) REFERENCES game (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE game ADD CONSTRAINT FK_GAME_LOSER_TO_GAME FOREIGN KEY (loser_to_game_id) REFERENCES game (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // Drop foreign keys on game
        $this->addSql('ALTER TABLE game DROP CONSTRAINT FK_GAME_BRACKET');
        $this->addSql('ALTER TABLE game DROP CONSTRAINT FK_GAME_WINNER_TO_GAME');
        $this->addSql('ALTER TABLE game DROP CONSTRAINT FK_GAME_LOSER_TO_GAME');

        // Drop indexes on game
        $this->addSql('DROP INDEX IDX_game_bracket_id');
        $this->addSql('DROP INDEX IDX_game_winner_to_game_id');
        $this->addSql('DROP INDEX IDX_game_loser_to_game_id');

        // Drop bracket_entry table (and its indexes/FKs via CASCADE)
        $this->addSql('DROP TABLE bracket_entry');

        // Drop bracket table (and its indexes/FKs via CASCADE)
        $this->addSql('DROP TABLE bracket');

        // Remove bracket-related columns from game
        $this->addSql(<<<'SQL'
            ALTER TABLE game
                DROP COLUMN bracket_id,
                DROP COLUMN round,
                DROP COLUMN bracket_position,
                DROP COLUMN is_third_place_match,
                DROP COLUMN winner_to_game_id,
                DROP COLUMN winner_to_slot,
                DROP COLUMN loser_to_game_id,
                DROP COLUMN loser_to_slot
        SQL);

        // Restore NOT NULL constraints on game.division_id and game.week
        $this->addSql('ALTER TABLE game ALTER division_id SET NOT NULL');
        $this->addSql('ALTER TABLE game ALTER week SET NOT NULL');
    }
}
