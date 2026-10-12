<?php

namespace App\Domain\Mod\Contracts;

/**
 * A rule that judges from the Track and Member alone, with no per-Track queries or network calls, so Search can run it.
 */
interface CheapRequestRule extends RequestRule {}
