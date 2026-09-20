<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260914170000 extends AbstractMigration
{
    public function getDescription(): string { return 'Dates et durée de lecture déclarées par la source'; }
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article ADD source_published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ADD source_modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ADD source_reading_minutes INT DEFAULT NULL');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article DROP source_published_at, DROP source_modified_at, DROP source_reading_minutes');
    }
}
