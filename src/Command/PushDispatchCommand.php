<?php
namespace App\Command;

use App\Entity\Article;
use App\Entity\ArticleFeedback;
use App\Service\PreferenceService;
use App\Service\PushService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:push:dispatch', description: 'Envoie les nouveaux articles selon les préférences de chaque appareil.')]
final class PushDispatchCommand extends Command
{
    public function __construct(private Connection $db, private EntityManagerInterface $em, private PreferenceService $preferences, private PushService $push) { parent::__construct(); }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->db->fetchOne('SELECT pg_try_advisory_lock(974202609)')) { return Command::SUCCESS; }
        $sent = 0;
        try {
            $max = (int) $this->db->fetchOne('SELECT COALESCE(MAX(id), 0) FROM article');
            foreach ($this->db->fetchAllAssociative('SELECT * FROM push_subscription ORDER BY id') as $row) {
                if ($row['last_sent_at'] && new \DateTimeImmutable($row['last_sent_at']) > new \DateTimeImmutable('-1 hour')) { continue; }
                $preference = $this->preferences->getOrCreate($row['owner_key']);
                if ($preference->getFrequency() === 'daily') { continue; }
                $articles = $this->em->getRepository(Article::class)->createQueryBuilder('a')
                    ->where('a.id > :cursor AND a.id <= :max AND a.isDemo = false AND a.publishedAt >= :recent')
                    ->setParameter('cursor', $row['last_article_id'])->setParameter('max', $max)
                    ->setParameter('recent', new \DateTimeImmutable('-24 hours'))
                    ->orderBy('a.id', 'DESC')->setMaxResults(500)->getQuery()->getResult();
                $matching = array_values(array_filter($articles, fn (Article $a) => $preference->matches($a)
                    && !$this->em->getRepository(ArticleFeedback::class)->findOneBy(['ownerKey' => $row['owner_key'], 'article' => $a, 'interested' => false])));
                if (!$matching) { $this->db->update('push_subscription', ['last_article_id' => $max], ['id' => $row['id']]); continue; }
                $first = $matching[0];
                try {
                    $result = $this->push->send(json_decode($row['subscription'], true), [
                        'title' => count($matching) > 1 ? count($matching).' nouvelles actualités · InfoTrak' : 'InfoTrak · '.$first->getCategory(),
                        'body' => mb_substr($first->getTitle(), 0, 180),
                        'url' => '/article/'.rawurlencode($first->getSlug()), 'tag' => 'infotrak-news',
                    ]);
                } catch (\Throwable) { $output->writeln('Un envoi a échoué; nouvel essai au prochain passage.'); continue; }
                if ($result === 'expired') { $this->db->delete('push_subscription', ['id' => $row['id']]); }
                if ($result === 'sent') {
                    ++$sent;
                    $this->db->update('push_subscription', ['last_article_id' => $max, 'last_sent_at' => date('Y-m-d H:i:s')], ['id' => $row['id']]);
                }
            }
        } finally { $this->db->fetchOne('SELECT pg_advisory_unlock(974202609)'); }
        $output->writeln($sent.' regroupement(s) envoyé(s).');
        return Command::SUCCESS;
    }
}
