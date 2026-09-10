<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910103000 extends AbstractMigration
{
    public function getDescription(): string { return 'Conserver intégralement les longs liens des agrégateurs RSS.'; }
    public function up(Schema $schema): void { $this->addSql('ALTER TABLE article ALTER source_url TYPE TEXT'); }
    public function down(Schema $schema): void { $this->throwIrreversibleMigrationException('Le retour à 500 caractères tronquerait des liens.'); }
}
