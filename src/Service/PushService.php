<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Web Push: seuls les services de livraison des navigateurs sont autorisés. */
final class PushService
{
    public function __construct(private Connection $db, #[Autowire('%kernel.secret%')] private string $secret) {}

    public static function validSubscription(array $data): bool
    {
        $url = $data['endpoint'] ?? null;
        if (!is_string($url) || strlen($url) > 2048) { return false; }
        $parts = parse_url($url);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || isset($parts['fragment'])) { return false; }
        $host = strtolower($parts['host'] ?? '');
        $allowed = in_array($host, ['fcm.googleapis.com', 'updates.push.services.mozilla.com', 'web.push.apple.com'], true)
            || str_ends_with($host, '.push.services.mozilla.com') || str_ends_with($host, '.notify.windows.com');
        if (!$allowed) { return false; }
        foreach (['p256dh' => 65, 'auth' => 16] as $key => $length) {
            $value = $data['keys'][$key] ?? null;
            if (!is_string($value) || !preg_match('/^[A-Za-z0-9_\-]+=*$/D', $value)) { return false; }
            $bytes = base64_decode(strtr($value, '-_', '+/'), true);
            if ($bytes === false || strlen($bytes) !== $length || ($key === 'p256dh' && $bytes[0] !== "\x04")) { return false; }
        }
        return true;
    }

    public function keys(): array
    {
        $row = $this->db->fetchAssociative('SELECT * FROM push_config WHERE id = 1');
        if (!$row) {
            $keys = VAPID::createVapidKeys();
            $iv = random_bytes(12);
            $encrypted = openssl_encrypt($keys['privateKey'], 'aes-256-gcm', hash('sha256', $this->secret, true), OPENSSL_RAW_DATA, $iv, $tag);
            if (false === $encrypted) { throw new \RuntimeException('Chiffrement des clés impossible.'); }
            $this->db->executeStatement('INSERT INTO push_config (id, public_key, private_key) VALUES (1, ?, ?) ON CONFLICT (id) DO NOTHING', [$keys['publicKey'], base64_encode($iv.$tag.$encrypted)]);
            $row = $this->db->fetchAssociative('SELECT * FROM push_config WHERE id = 1');
        }
        $encrypted = base64_decode($row['private_key'], true);
        $private = openssl_decrypt(substr($encrypted, 28), 'aes-256-gcm', hash('sha256', $this->secret, true), OPENSSL_RAW_DATA, substr($encrypted, 0, 12), substr($encrypted, 12, 16));
        if (false === $private) { throw new \RuntimeException('Clés push illisibles : vérifier APP_SECRET.'); }
        return ['publicKey' => $row['public_key'], 'privateKey' => $private];
    }

    public function subscribe(string $owner, array $data): void
    {
        if (!self::validSubscription($data)) { throw new \InvalidArgumentException('Abonnement du navigateur invalide.'); }
        $hash = hash('sha256', $data['endpoint']);
        $existing = $this->db->fetchAssociative('SELECT owner_key FROM push_subscription WHERE endpoint_hash = ?', [$hash]);
        if ($existing && $existing['owner_key'] !== $owner) { throw new \InvalidArgumentException('Cet appareil est lié à une autre session. Désactivez puis réactivez les notifications.'); }
        $max = (int) $this->db->fetchOne('SELECT COALESCE(MAX(id), 0) FROM article');
        $clean = ['endpoint' => $data['endpoint'], 'keys' => ['p256dh' => $data['keys']['p256dh'], 'auth' => $data['keys']['auth']]];
        if (!$existing && (int) $this->db->fetchOne('SELECT COUNT(*) FROM push_subscription WHERE owner_key = ?', [$owner]) >= 5) { throw new \InvalidArgumentException('Cinq appareils maximum par compte.'); }
        $this->db->executeStatement('INSERT INTO push_subscription (owner_key, endpoint_hash, subscription, created_at, last_article_id) VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?) ON CONFLICT (endpoint_hash) DO UPDATE SET subscription = EXCLUDED.subscription', [$owner, $hash, json_encode($clean, JSON_THROW_ON_ERROR), $max]);
    }

    public function unsubscribe(string $owner, string $endpoint): void
    {
        $this->db->delete('push_subscription', ['owner_key' => $owner, 'endpoint_hash' => hash('sha256', $endpoint)]);
    }

    /** Ne journalise jamais les URL privées des appareils. Pas de redirections HTTP. */
    public function send(array $subscription, array $payload): string
    {
        if (!self::validSubscription($subscription)) { return 'expired'; }
        $client = new \GuzzleHttp\Client(['timeout' => 10, 'allow_redirects' => false]);
        $sender = new WebPush(['VAPID' => ['subject' => 'https://infotrak-re.onrender.com'] + $this->keys()], ['TTL' => 3600, 'urgency' => 'normal'], $client);
        $report = $sender->sendOneNotification(Subscription::create($subscription), json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        return $report->isSuccess() ? 'sent' : ($report->isSubscriptionExpired() ? 'expired' : 'failed');
    }
}
