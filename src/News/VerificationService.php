<?php

namespace App\News;

use App\Entity\Article;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Vérification automatique des infos : pour chaque article avec un lien source,
 * on récupère la page d'origine et on contrôle que son contenu corrobore le titre.
 *
 * - page joignable + mots du titre retrouvés → « Source retrouvée » (pas une vérification des faits)
 * - page joignable mais sans recoupement → « À recouper »
 * - page injoignable → « Source inaccessible »
 */
final class VerificationService
{
    private const STOPWORDS = [
        'le', 'la', 'les', 'de', 'des', 'du', 'un', 'une', 'et', 'est', 'sont', 'dans', 'pour',
        'avec', 'sur', 'plus', 'par', 'au', 'aux', 'ce', 'cette', 'ces', 'son', 'sa', 'ses',
        'qui', 'que', 'dont', 'mais', 'comme', 'tout', 'tous', 'toute', 'toutes', 'entre',
        'vers', 'chez', 'sans', 'sous', 'leur', 'leurs', 'etre', 'avoir', 'fait', 'faire',
        'plus', 'moins', 'tres', 'aussi', 'encore', 'alors', 'donc', 'lors', 'apres', 'avant',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $http,
    ) {}

    /**
     * @return array{checked: int, verified: int, recheck: int, unreachable: int}
     */
    public function verifyRecent(int $limit = 50): array
    {
        /** @var ArticleRepository $repo */
        $repo = $this->em->getRepository(Article::class);
        $stats = ['checked' => 0, 'verified' => 0, 'recheck' => 0, 'unreachable' => 0];

        foreach ($repo->findRecent($limit) as $article) {
            if (null === $article->getSourceUrl()) {
                continue;
            }
            ++$stats['checked'];
            ++$stats[$this->verifyArticle($article, false)];
        }
        $this->em->flush();

        return $stats;
    }

    /** Vérifie un article (applique + enregistre) : 'verified', 'recheck' ou 'unreachable'. */
    public function verifyArticle(Article $article, bool $flush = true): string
    {
        // Les réseaux sociaux sont des discussions, pas des infos vérifiées : étiquette dédiée.
        if ('social' === $article->getSource()?->getType()) {
            $article->setVerified(false)->setVerificationLabel('Réseau social');
            if ($flush) {
                $this->em->flush();
            }

            return 'recheck';
        }

        $result = $this->check($article->getSourceUrl(), $article->getTitle());

        if ('verified' === $result) {
            $article->setVerified(false)->setVerificationLabel('Source retrouvée');
        } elseif ('recheck' === $result) {
            $article->setVerified(false)->setVerificationLabel('À recouper');
        } else {
            $article->setVerified(false)->setVerificationLabel('Source inaccessible');
        }

        if ($flush) {
            $this->em->flush();
        }

        return $result;
    }

    /** Contrôle pur (sans BDD) : la page source corrobore-t-elle le titre ? */
    public function check(?string $url, string $title): string
    {
        if (null === $url || '' === $url || !filter_var($url, FILTER_VALIDATE_URL)) {
            return 'unreachable';
        }

        try {
            $response = $this->http->request('GET', $url, [
                'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; InfoTrak/1.0; verification)'],
                'timeout' => 10,
                'max_duration' => 15,
                'max_redirects' => 3,
            ]);
            if (200 !== $response->getStatusCode()) {
                return 'unreachable';
            }
            $page = FeedClassifier::normalize(mb_substr(
                mb_strtolower(html_entity_decode(strip_tags($response->getContent(false)), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                0,
                300000,
            ));
        } catch (\Throwable) {
            return 'unreachable';
        }

        $keywords = self::significantWords($title);
        if ([] === $keywords) {
            return 'recheck';
        }
        $found = 0;
        foreach ($keywords as $word) {
            if (str_contains($page, $word)) {
                ++$found;
            }
        }

        // Au moins 3 mots significatifs (ou la moitié pour les titres courts).
        $needed = min(3, (int) ceil(\count($keywords) / 2));

        return $found >= $needed ? 'verified' : 'recheck';
    }

    /** @return string[] mots significatifs du titre, normalisés. */
    public static function significantWords(string $title): array
    {
        $words = preg_split('/[^a-z0-9]+/', FeedClassifier::normalize($title)) ?: [];
        $significant = [];
        foreach ($words as $word) {
            $word = trim($word);
            if (mb_strlen($word) > 4 && !\in_array($word, self::STOPWORDS, true) && !\in_array($word, $significant, true)) {
                $significant[] = $word;
            }
        }

        return \array_slice($significant, 0, 8);
    }
}
