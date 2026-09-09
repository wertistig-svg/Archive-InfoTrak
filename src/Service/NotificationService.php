<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\Notification;
use App\Entity\Preference;
use App\Repository\ArticleRepository;
use App\Repository\NotificationRepository;
use App\Repository\ArticleFeedbackRepository;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NotificationRepository $notifications,
        private readonly ArticleRepository $articles,
        private readonly ArticleFeedbackRepository $feedbacks,
    ) {}

    public function notifyForArticle(Article $article, ?Preference $preference = null): ?Notification
    {
        if (null === $preference || $article->isDemo() || Preference::FREQUENCY_DAILY === $preference->getFrequency() || !$preference->matches($article)) {
            return null;
        }

        if ($this->feedbacks->findOneBy(['article' => $article, 'ownerKey' => $preference->getOwnerKey(), 'interested' => false])) {
            return null;
        }

        $already = $this->notifications->findOneBy(['article' => $article, 'ownerKey' => $preference->getOwnerKey()]);
        if (null !== $already) {
            return null;
        }

        $notification = (new Notification())
            ->setOwnerKey($preference->getOwnerKey())
            ->setTitle($article->getTitle())
            ->setMessage(self::summarize((string) ($article->getExcerpt() ?? $article->getTitle())))
            ->setCategory($article->getCategory())
            ->setLevel($article->isImportant() ? 'high' : 'info')
            ->setArticle($article);

        $this->em->persist($notification);
        $this->em->flush();

        return $notification;
    }

    /** Crée les notifications manquantes pour les articles récents correspondant aux préférences. */
    public function generateForRecent(Preference $preference, int $limit = 20): int
    {
        $articles = $this->articles->findRecent($limit);
        $count = 0;
        foreach ($articles as $article) {
            if (null !== $this->notifyForArticle($article, $preference)) {
                ++$count;
            }
        }

        return $count;
    }

    /** Résumé clair : texte nettoyé, coupé proprement sur un mot. */
    public static function summarize(string $text, int $max = 160): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', $text));
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        $cut = mb_substr($text, 0, $max);
        $lastSpace = mb_strrpos($cut, ' ');
        if (false !== $lastSpace && $lastSpace > (int) ($max * 0.5)) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        return rtrim($cut, " \t\n\r\0\x0B,;:").'…';
    }

    public function markAllAsRead(string $ownerKey): int
    {
        $unread = $this->notifications->findBy(['isRead' => false, 'ownerKey' => $ownerKey]);

        foreach ($unread as $notification) {
            $notification->markAsRead();
        }
        $this->em->flush();

        return \count($unread);
    }
}
