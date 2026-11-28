<?php

namespace App\Mods\AiRequestReview;

class HostResolver
{
    /**
     * @return list<string>
     */
    public function resolve(string $host): array
    {
        $addresses = [];
        foreach ([DNS_A => 'ip', DNS_AAAA => 'ipv6'] as $type => $field) {
            $records = @dns_get_record($host, $type);
            foreach (is_array($records) ? $records : [] as $record) {
                if (is_string($record[$field] ?? null)) {
                    $addresses[] = $record[$field];
                }
            }
        }

        return $addresses;
    }
}
