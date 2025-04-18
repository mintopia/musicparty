<?php

if (!defined('format_ms')) {
    function format_ms($input): string
    {
        $input = (int) $input;
        $mins = floor($input / 60000);
        $secs = sprintf('%02d', floor($input % 60000) / 1000);
        return "{$mins}:{$secs}";
    }
}
