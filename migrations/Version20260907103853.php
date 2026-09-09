<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260907103853 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Isolation des notifications et identification des exemples de démonstration.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article ADD is_demo BOOLEAN DEFAULT false NOT NULL');
        $this->addSql("UPDATE article SET is_demo = true WHERE slug IN ('vivatech-10-entrepreneurs-pei', 'cyber-arnaques-livraison', 'emploi-ouest-ile', 'tgs-studios-fr', 'associations-jeunes', 'cnil-donnees', 'secteurs-recrutent', 'chine-ia-shanghai', 'asie-cables-sous-marins', 're-lagons-sentinelles')");
        $this->addSql('ALTER TABLE article ALTER is_verified SET DEFAULT false');
        $this->addSql('ALTER TABLE article ALTER verification_label SET DEFAULT \'À recouper\'');
        $this->addSql('ALTER TABLE notification ADD owner_key VARCHAR(64) DEFAULT \'legacy\' NOT NULL');
        // Les anciennes notifications n'ont pas de destinataire connu : elles restent privées et orphelines.
        $this->addSql("UPDATE notification SET owner_key = 'legacy-' || id");
        $this->addSql('CREATE INDEX IDX_BF5476CA255BB5E3DA46F46 ON notification (owner_key, is_read)');
        $this->addSql('CREATE UNIQUE INDEX uniq_notification_owner_article ON notification (owner_key, article_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article DROP is_demo');
        $this->addSql('ALTER TABLE article ALTER is_verified SET DEFAULT true');
        $this->addSql('ALTER TABLE article ALTER verification_label SET DEFAULT \'Vérifiée\'');
        $this->addSql('DROP INDEX IDX_BF5476CA255BB5E3DA46F46');
        $this->addSql('DROP INDEX uniq_notification_owner_article');
        $this->addSql('ALTER TABLE notification DROP owner_key');
    }
}
