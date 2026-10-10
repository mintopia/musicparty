<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartyThemeResource extends JsonResource
{
    /**
     * @param  array{light: array<string, string>, dark: array<string, string>, font: string, logo_url: ?string, logo_dark_url: ?string, background_url: ?string, tv_layout: string, overrides: array{light: array<string, string>, dark: array<string, string>, font: ?string}}  $theme
     * @param  list<array{scheme: string, pair: string, ratio: float}>  $warnings
     */
    public function __construct(array $theme, private readonly array $warnings)
    {
        parent::__construct($theme);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'light' => $this->resource['light'],
            'dark' => $this->resource['dark'],
            'font' => $this->resource['font'],
            'logo_url' => $this->resource['logo_url'],
            'logo_dark_url' => $this->resource['logo_dark_url'],
            'background_url' => $this->resource['background_url'],
            'tv_layout' => $this->resource['tv_layout'],
            'overrides' => $this->resource['overrides'],
            'warnings' => $this->warnings,
        ];
    }
}
