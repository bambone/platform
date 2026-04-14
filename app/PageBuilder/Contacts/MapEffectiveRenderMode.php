<?php

declare(strict_types=1);

namespace App\PageBuilder\Contacts;

enum MapEffectiveRenderMode: string
{
    case None = 'none';
    case ButtonOnly = 'button_only';
    case EmbedOnly = 'embed_only';
    case EmbedAndButton = 'embed_and_button';
}
