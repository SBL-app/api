<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260323105526 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE match_report_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE match_result_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE match_report (id INT NOT NULL, game_id INT NOT NULL, team_id INT NOT NULL, requested_by_id INT NOT NULL, reason TEXT DEFAULT NULL, is_admin_forced BOOLEAN DEFAULT false NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_65408E85E48FD905 ON match_report (game_id)');
        $this->addSql('CREATE INDEX IDX_65408E85296CD8AE ON match_report (team_id)');
        $this->addSql('CREATE INDEX IDX_65408E854DA1E751 ON match_report (requested_by_id)');
        $this->addSql('COMMENT ON COLUMN match_report.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE match_result (id INT NOT NULL, game_id INT NOT NULL, submitted_by_id INT NOT NULL, team_id INT NOT NULL, score1 INT NOT NULL, score2 INT NOT NULL, status VARCHAR(20) DEFAULT \'pending\' NOT NULL, contest_reason TEXT DEFAULT NULL, validated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, reminder_sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_B2053812E48FD905 ON match_result (game_id)');
        $this->addSql('CREATE INDEX IDX_B205381279F7D87D ON match_result (submitted_by_id)');
        $this->addSql('CREATE INDEX IDX_B2053812296CD8AE ON match_result (team_id)');
        $this->addSql('COMMENT ON COLUMN match_result.validated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN match_result.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN match_result.reminder_sent_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE match_report ADD CONSTRAINT FK_65408E85E48FD905 FOREIGN KEY (game_id) REFERENCES game (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE match_report ADD CONSTRAINT FK_65408E85296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE match_report ADD CONSTRAINT FK_65408E854DA1E751 FOREIGN KEY (requested_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE match_result ADD CONSTRAINT FK_B2053812E48FD905 FOREIGN KEY (game_id) REFERENCES game (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE match_result ADD CONSTRAINT FK_B205381279F7D87D FOREIGN KEY (submitted_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE match_result ADD CONSTRAINT FK_B2053812296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE push_subscription ALTER id DROP DEFAULT');
        $this->addSql('ALTER INDEX unique_push_subscription_endpoint RENAME TO UNIQ_562830F3C4420F7B');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE match_report_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE match_result_id_seq CASCADE');
        $this->addSql('ALTER TABLE match_report DROP CONSTRAINT FK_65408E85E48FD905');
        $this->addSql('ALTER TABLE match_report DROP CONSTRAINT FK_65408E85296CD8AE');
        $this->addSql('ALTER TABLE match_report DROP CONSTRAINT FK_65408E854DA1E751');
        $this->addSql('ALTER TABLE match_result DROP CONSTRAINT FK_B2053812E48FD905');
        $this->addSql('ALTER TABLE match_result DROP CONSTRAINT FK_B205381279F7D87D');
        $this->addSql('ALTER TABLE match_result DROP CONSTRAINT FK_B2053812296CD8AE');
        $this->addSql('DROP TABLE match_report');
        $this->addSql('DROP TABLE match_result');
        $this->addSql('CREATE SEQUENCE push_subscription_id_seq');
        $this->addSql('SELECT setval(\'push_subscription_id_seq\', (SELECT MAX(id) FROM push_subscription))');
        $this->addSql('ALTER TABLE push_subscription ALTER id SET DEFAULT nextval(\'push_subscription_id_seq\')');
        $this->addSql('ALTER INDEX uniq_562830f3c4420f7b RENAME TO unique_push_subscription_endpoint');
    }
}
