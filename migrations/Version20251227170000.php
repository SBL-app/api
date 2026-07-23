<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251227170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add captainUser to team and create match_proposal table';
    }

    public function up(Schema $schema): void
    {
        // Add captain_user_id to team table
        $this->addSql(<<<'SQL'
            ALTER TABLE team ADD captain_user_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE team ADD CONSTRAINT FK_C4E0A61F9C4A17B7 FOREIGN KEY (captain_user_id) REFERENCES users (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_C4E0A61F9C4A17B7 ON team (captain_user_id)
        SQL);

        // Create match_proposal table
        $this->addSql(<<<'SQL'
            CREATE TABLE match_proposal (
                id INT AUTO_INCREMENT NOT NULL,
                game_id INT NOT NULL,
                proposer_id INT NOT NULL,
                receiver_id INT NOT NULL,
                counter_to_id INT DEFAULT NULL,
                proposed_date DATETIME NOT NULL,
                status VARCHAR(20) NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX IDX_MATCH_PROPOSAL_GAME (game_id),
                INDEX IDX_MATCH_PROPOSAL_PROPOSER (proposer_id),
                INDEX IDX_MATCH_PROPOSAL_RECEIVER (receiver_id),
                INDEX IDX_MATCH_PROPOSAL_COUNTER (counter_to_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE match_proposal ADD CONSTRAINT FK_MATCH_PROPOSAL_GAME FOREIGN KEY (game_id) REFERENCES game (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE match_proposal ADD CONSTRAINT FK_MATCH_PROPOSAL_PROPOSER FOREIGN KEY (proposer_id) REFERENCES users (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE match_proposal ADD CONSTRAINT FK_MATCH_PROPOSAL_RECEIVER FOREIGN KEY (receiver_id) REFERENCES users (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE match_proposal ADD CONSTRAINT FK_MATCH_PROPOSAL_COUNTER FOREIGN KEY (counter_to_id) REFERENCES match_proposal (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // Drop match_proposal table
        $this->addSql(<<<'SQL'
            ALTER TABLE match_proposal DROP FOREIGN KEY FK_MATCH_PROPOSAL_GAME
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE match_proposal DROP FOREIGN KEY FK_MATCH_PROPOSAL_PROPOSER
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE match_proposal DROP FOREIGN KEY FK_MATCH_PROPOSAL_RECEIVER
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE match_proposal DROP FOREIGN KEY FK_MATCH_PROPOSAL_COUNTER
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE match_proposal
        SQL);

        // Remove captain_user_id from team table
        $this->addSql(<<<'SQL'
            ALTER TABLE team DROP FOREIGN KEY FK_C4E0A61F9C4A17B7
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_C4E0A61F9C4A17B7 ON team
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE team DROP captain_user_id
        SQL);
    }
}
