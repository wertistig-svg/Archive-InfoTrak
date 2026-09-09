<?php

namespace App\Command;

use App\Entity\Article;
use App\Entity\Source;
use App\Repository\ArticleRepository;
use App\Repository\SourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed', description: 'Insère les sources et articles de démonstration InfoTrak (idempotent).')]
class SeedCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SourceRepository $sources,
        private readonly ArticleRepository $articles,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $sourceRows = [
            ['Linfo.re', 'linfo-re', 'https://www.linfo.re', 'press', 84],
            ['Zinfos974', 'zinfos974', 'https://www.zinfos974.com', 'press', 82],
            ['Cybermalveillance.gouv.fr', 'cybermalveillance', 'https://www.cybermalveillance.gouv.fr', 'official', 98],
            ['CNIL', 'cnil', 'https://www.cnil.fr', 'official', 97],
            ['France Travail', 'france-travail', 'https://www.francetravail.fr', 'public', 93],
            ['Le Monde', 'le-monde', 'https://www.lemonde.fr', 'press', 88],
            ['Xinhua / Asie-Pacifique', 'xinhua-asie', 'https://french.xinhuanet.com', 'press', 76],
        ];

        foreach ($sourceRows as [$name, $slug, $url, $type, $score]) {
            if (null !== $this->sources->findOneBy(['slug' => $slug])) {
                continue;
            }
            $source = (new Source())
                ->setName($name)
                ->setSlug($slug)
                ->setWebsiteUrl($url)
                ->setType($type)
                ->setReliabilityScore($score);
            $this->em->persist($source);
        }
        $this->em->flush();

        $now = new \DateTimeImmutable();
        $minutesAgo = static fn (int $m): \DateTimeImmutable => $now->modify(sprintf('-%d minutes', $m));

        // slug, titre, résumé, catégorie, lieu, sourceSlug, sourceUrl, publié il y a (min, entier) ou date fixe (string), vérifié, label, important, videoUrl
        // Les vidéos de démo sans rapport avec l'article ont été retirées : une vidéo n'est
        // attachée que si elle illustre vraiment le sujet (commande app:article:set-video).
        $articleRows = [
            ['vivatech-10-entrepreneurs-pei', 'Vivatech : 10 entrepreneurs péi au plus grand salon de l’innovation technologique à Paris', 'Dix entrepreneurs réunionnais au salon Viva Tech parmi 28 000 exposants pour défendre le savoir-faire des territoires ultramarins et conquérir des marchés à l’international.', 'La Réunion', 'La Réunion', 'linfo-re', 'https://www.linfo.re/la-reunion/societe/10-entrepreneurs-reunionnais-au-plus-grand-salon-de-l-innovation-technologique-a-paris', '2024-05-25 19:11:00', true, 'Vérifiée', false, null],
            ['cyber-arnaques-livraison', 'Une alerte sur les arnaques par faux messages de livraison', 'Cybermalveillance alerte sur les SMS frauduleux invitant à payer de faux frais de livraison.', 'Cybersécurité', 'France', 'cybermalveillance', 'https://www.cybermalveillance.gouv.fr', 48, true, 'Vérifiée', true, null],
            ['emploi-ouest-ile', '186 nouvelles offres publiées dans l’ouest de l’île', 'France Travail publie 186 offres dans l’ouest : BTP, santé et numérique en tête.', 'Emploi', 'La Réunion', 'france-travail', 'https://www.francetravail.fr', 60, true, 'Source publique', false, null],
            ['tgs-studios-fr', 'Les studios indépendants français à l’honneur au Tokyo Game Show', 'Plusieurs studios français présentent leurs jeux au salon de Tokyo, vitrine pour la création européenne.', 'Jeux', 'Japon', 'le-monde', 'https://www.lemonde.fr', 120, true, 'Recoupée', false, null],
            ['associations-jeunes', 'Les associations locales renforcent l’accompagnement des jeunes', 'Zinfos974 : de nouvelles permanences d’accompagnement pour les 16-25 ans à Saint-Denis.', 'La Réunion', 'La Réunion', 'zinfos974', 'https://www.zinfos974.com', 18, true, 'Vérifiée', false, null],
            ['cnil-donnees', 'Protéger ses données personnelles : les réflexes à adopter', 'La CNIL publie un guide pratique : mots de passe, double authentification, paramètres de confidentialité.', 'Cybersécurité', 'France', 'cnil', 'https://www.cnil.fr', 52, true, 'Source officielle', true, null],
            ['secteurs-recrutent', 'Les secteurs qui recrutent cette semaine à La Réunion', 'Données publiques France Travail : commerce, aide à la personne et hôtellerie en hausse.', 'Emploi', 'La Réunion', 'france-travail', 'https://www.francetravail.fr', 65, true, 'Données publiques', false, null],
            ['chine-ia-shanghai', 'À Shanghai, l’IA appliquée au climat attire les labos européens', 'Un sommet sino-européen sur l’IA et le climat réunit chercheurs et industriels à Shanghai.', 'Environnement', 'Chine', 'xinhua-asie', 'https://french.xinhuanet.com', 95, true, 'Recoupée', true, null],
            ['asie-cables-sous-marins', 'Câbles sous-marins en Asie-Pacifique : un nouveau tronçon vers l’océan Indien', 'Le projet renforce la liaison entre Singapour, Jakarta et La Réunion pour sécuriser le trafic internet.', 'Cybersécurité', 'Asie', 'xinhua-asie', 'https://french.xinhuanet.com', 150, false, 'À recouper', false, null],
            ['re-lagons-sentinelles', 'Lagons sentinelles : La Réunion rejoint un réseau d’observation international', 'Le programme associe la Réunion, Maurice et l’Indonésie pour suivre la santé des récifs coralliens.', 'Environnement', 'International', 'le-monde', 'https://www.lemonde.fr', 200, true, 'Vérifiée', false, null],
        ];

        $created = 0;
        $updated = 0;
        foreach ($articleRows as [$slug, $title, $excerpt, $category, $place, $sourceSlug, $sourceUrl, $when, $verified, $label, $important, $videoUrl]) {
            $existing = $this->articles->findOneBy(['slug' => $slug]);
            if (null !== $existing) {
                // Rattrapage : les données de démo évoluent (sources réelles, vidéos retirées).
                $existing->setDemo(true)->setTitle($title)
                    ->setExcerpt($excerpt)
                    ->setContent($excerpt)
                    ->setCategory($category)
                    ->setPlace($place)
                    ->setSource($this->sources->findOneBy(['slug' => $sourceSlug]))
                    ->setSourceUrl($sourceUrl)
                    ->setVerified($verified)
                    ->setVerificationLabel($label)
                    ->setImportant($important)
                    ->setVideoUrl($videoUrl);
                ++$updated;
                continue;
            }
            $source = $this->sources->findOneBy(['slug' => $sourceSlug]);
            $article = (new Article())
                ->setDemo(true)
                ->setTitle($title)
                ->setSlug($slug)
                ->setExcerpt($excerpt)
                ->setContent($excerpt)
                ->setCategory($category)
                ->setPlace($place)
                ->setSource($source)
                ->setSourceUrl($sourceUrl)
                ->setPublishedAt(\is_int($when) ? $minutesAgo($when) : new \DateTimeImmutable($when))
                ->setVerified($verified)
                ->setVerificationLabel($label)
                ->setImportant($important)
                ->setVideoUrl($videoUrl);
            $this->em->persist($article);
            ++$created;
        }
        $this->em->flush();

        $io->success(sprintf('Démonstration : %d exemple(s) créé(s), %d mis à jour. Ils sont masqués par défaut dans le fil.', $created, $updated));

        return Command::SUCCESS;
    }
}
