<?php

namespace App\Domain\Theming\Models;

use Database\Factories\InstanceThemeFactory;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Unguarded]
#[UseFactory(InstanceThemeFactory::class)]
class InstanceTheme extends Model
{
    /** @use HasFactory<InstanceThemeFactory> */
    use HasFactory;

    protected $casts = ['tokens' => 'array'];
}
