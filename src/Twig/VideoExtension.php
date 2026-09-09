<?php

namespace App\Twig;

use App\InfoTrak\VideoEmbed;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class VideoExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            // Retourne null si l'URL n'est pas une vidéo intégrable.
            new TwigFunction('video_embed', [VideoEmbed::class, 'parse']),
        ];
    }
}
