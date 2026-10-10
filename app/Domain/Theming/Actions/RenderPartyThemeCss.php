<?php

namespace App\Domain\Theming\Actions;

use App\Domain\Party\Models\Party;

class RenderPartyThemeCss
{
    public function __construct(private readonly GetPartyTheme $getPartyTheme, private readonly RenderThemeCss $renderThemeCss) {}

    public function handle(Party $party): string
    {
        return $this->renderThemeCss->fromTheme($this->getPartyTheme->handle($party));
    }
}
