<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260310100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add push_subscription table, game.reminder_sent_at and division.is_finalized for PWA push notifications';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE push_subscription (
                id SERIAL PRIMARY KEY,
                user_id INT NOT NULL,
                endpoint VARCHAR(2048) NOT NULL,
                p256dh_key VARCHAR(512) NOT NULL,
                auth_token VARCHAR(255) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT fk_push_subscription_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                CONSTRAINT unique_push_subscription_endpoint UNIQUE (endpoint)
            )
        SQL);

        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN push_subscription.created_at IS '(DC2Type:datetime_immutable)'
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE game ADD COLUMN reminder_sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE division ADD COLUMN is_finalized BOOLEAN NOT NULL DEFAULT false
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DROP TABLE push_subscription
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE game DROP COLUMN reminder_sent_at
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE division DROP COLUMN is_finalized
        SQL);
    }
}
