<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstanceThemeResource extends JsonResource
{
    /**
     * @param  array{light: array<string, string>, dark: array<string, string>, font: string}  $theme
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
            'warnings' => $this->warnings,
        ];
    }
}
