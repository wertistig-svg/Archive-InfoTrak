<?php

namespace App\Tests\News;

use App\News\FeedClassifier;
use PHPUnit\Framework\TestCase;

class FeedClassifierTest extends TestCase
{
    public function testCyberCategoryDetected(): void
    {
        $this->assertSame('Cybersécurité', FeedClassifier::categoryFor('Alerte aux arnaques par SMS frauduleux', 'La Réunion'));
        $this->assertSame('Cybersécurité', FeedClassifier::categoryFor('Protéger ses données personnelles : les réflexes', 'La Réunion'));
    }

    public function testEmploiAndEnvironmentDetected(): void
    {
        $this->assertSame('Emploi', FeedClassifier::categoryFor('186 nouvelles offres publiées dans l’ouest', 'La Réunion'));
        $this->assertSame('Environnement', FeedClassifier::categoryFor('Le cyclone Gezani menace Tamatave', 'La Réunion'));
        $this->assertSame('Environnement', FeedClassifier::categoryFor('Lagons sentinelles : suivi des récifs coralliens', 'International'));
    }

    public function testGamesDetectedButEnjeuxIsNotAGame(): void
    {
        $this->assertSame('Jeux', FeedClassifier::categoryFor('Festival du jeu vidéo à Saint-Denis', 'La Réunion'));
        // « enjeux » ne doit pas déclencher la catégorie Jeux.
        $this->assertSame('La Réunion', FeedClassifier::categoryFor('Les enjeux du numérique réunionnais', 'La Réunion'));
    }

    public function testDefaultCategoryKeptWithoutKeyword(): void
    {
        $this->assertSame('Emploi', FeedClassifier::categoryFor('Conseil municipal : le budget est voté', 'Emploi'));
    }

    public function testNewSubjectsDoNotAllBecomeGames(): void
    {
        self::assertSame('Sport', FeedClassifier::categoryFor('Le championnat de football reprend', 'Jeux'));
        self::assertSame('Musique', FeedClassifier::categoryFor('Un concert à Saint-Paul', 'Jeux'));
        self::assertSame('Streaming & créateurs', FeedClassifier::categoryFor('ZEVENT : les dons progressent sur Twitch', 'Jeux'));
        self::assertSame('Tech & IA', FeedClassifier::categoryFor('Les usages de l’intelligence artificielle', 'La Réunion'));
        self::assertSame('La Réunion', FeedClassifier::categoryFor('Ils filment une cascade impressionnante', 'La Réunion'));
        self::assertSame('Sport', FeedClassifier::categoryFor('Ils filment un saut en base jump', 'Jeux'));
    }

    public function testReunionMentionWinsOnNationalFeed(): void
    {
        $this->assertSame('La Réunion', FeedClassifier::placeFor('Tour cycliste de La Réunion : le prologue', 'France'));
        $this->assertSame('La Réunion', FeedClassifier::placeFor('Leptospirose : un décès à Saint-Denis - Zinfos974', 'France'));
    }

    public function testNationalFeedsStayInFrance(): void
    {
        $this->assertSame('France', FeedClassifier::placeFor('Sommet sino-européen sur l’IA à Shanghai', 'France'));
        $this->assertSame('France', FeedClassifier::placeFor('Câbles sous-marins entre Singapour et Jakarta', 'France'));
        $this->assertSame('France', FeedClassifier::placeFor('Les studios indépendants français en force', 'France'));
    }

    public function testSlugifyIsStableAndAscii(): void
    {
        $slug = FeedClassifier::slugify('Le numérique réunionnais accélère : de nouveaux projets !');
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug);
        $this->assertStringStartsWith('le-numerique-reunionnais-accelere', $slug);
    }

    public function testExcerptStripsHtml(): void
    {
        $excerpt = FeedClassifier::excerptOf('<p>Bonjour <b>le monde</b> &amp; bienvenue !</p>');
        $this->assertSame('Bonjour le monde & bienvenue !', $excerpt);
    }
}
