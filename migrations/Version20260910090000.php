<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910090000 extends AbstractMigration
{
    public function getDescription(): string { return 'Abonnements Web Push privés et clés chiffrées persistantes.'; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE push_subscription (id SERIAL PRIMARY KEY, owner_key VARCHAR(64) NOT NULL, endpoint_hash VARCHAR(64) NOT NULL UNIQUE, subscription TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_article_id INT NOT NULL DEFAULT 0)');
        $this->addSql('CREATE INDEX push_owner_idx ON push_subscription (owner_key)');
        $this->addSql('CREATE TABLE push_config (id INT PRIMARY KEY, public_key TEXT NOT NULL, private_key TEXT NOT NULL)');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE push_subscription');
        $this->addSql('DROP TABLE push_config');
    }
}
