<?php

namespace App\Service;

use App\Entity\Preference;
use App\InfoTrak\Catalog;
use App\Repository\PreferenceRepository;
use Doctrine\ORM\EntityManagerInterface;

class PreferenceService
{
    public function __construct(
        private readonly PreferenceRepository $preferences,
        private readonly EntityManagerInterface $em,
    ) {}

    public function getOrCreate(string $ownerKey = 'default'): Preference
    {
        $preference = $this->preferences->findOneBy(['ownerKey' => $ownerKey]);
        if (null !== $preference) {
            return $preference;
        }

        $preference = (new Preference())
            ->setOwnerKey($ownerKey)
            ->setTopics([])
            ->setZones([])
            ->setFrequency(Preference::FREQUENCY_IMPORTANT);

        $this->em->persist($preference);
        $this->em->flush();

        return $preference;
    }

    /**
     * @param string[] $topics
     * @param string[] $zones
     */
    public function update(Preference $preference, array $topics, array $zones, string $frequency, ?string $notifyEmail = null): Preference
    {
        $topics = array_values(array_intersect($topics, Catalog::TOPICS));
        $zones = array_values(array_intersect($zones, Catalog::ZONES));

        $preference->setTopics($topics)->setZones($zones)->setFrequency($frequency);
        if (null !== $notifyEmail) {
            $preference->setNotifyEmail('' === $notifyEmail ? null : $notifyEmail);
        }

        $this->em->flush();

        return $preference;
    }
}
