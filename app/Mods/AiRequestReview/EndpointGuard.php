<?php

namespace App\Mods\AiRequestReview;

class EndpointGuard
{
    public function __construct(protected HostResolver $resolver) {}

    /**
     * Validates the endpoint and returns the addresses its host resolved to (empty for literals or when internal endpoints are allowed).
     *
     * @return list<string>
     */
    public function assertSafe(string $endpoint, bool $allowInternal = false): array
    {
        $parts = parse_url($endpoint);
        $host = is_array($parts) ? strtolower(trim((string) ($parts['host'] ?? ''), '[]')) : '';
        if (! in_array($parts['scheme'] ?? null, ['http', 'https'], true) || $host === '') {
            throw new DecisionModelException('AI review endpoint must be an http or https URL');
        }
        if ($allowInternal) {
            return [];
        }

        $internalName = $host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.internal') || str_ends_with($host, '.local');
        if ($internalName) {
            throw new DecisionModelException('AI review endpoint must not be an internal address');
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            if ($this->isInternal($host)) {
                throw new DecisionModelException('AI review endpoint must not be an internal address');
            }

            return [];
        }

        $addresses = $this->resolver->resolve($host);
        if ($addresses === []) {
            throw new DecisionModelException('AI review endpoint hostname did not resolve');
        }
        foreach ($addresses as $address) {
            if ($this->isInternal($address)) {
                throw new DecisionModelException('AI review endpoint must not resolve to an internal address');
            }
        }

        return $addresses;
    }

    /**
     * @param  list<string>  $addresses
     * @return array<string, mixed>
     */
    public function pinnedOptions(string $endpoint, array $addresses): array
    {
        $parts = parse_url($endpoint);
        if (! is_array($parts) || ! isset($parts['host'])) {
            return ['allow_redirects' => false];
        }
        if ($addresses === []) {
            return ['allow_redirects' => false];
        }
        $port = $parts['port'] ?? (($parts['scheme'] ?? 'https') === 'http' ? 80 : 443);
        $ip = str_contains($addresses[0], ':') ? "[{$addresses[0]}]" : $addresses[0];

        return ['allow_redirects' => false, 'curl' => [CURLOPT_RESOLVE => ["{$parts['host']}:{$port}:{$ip}"]]];
    }

    protected function isInternal(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
            || $this->isLinkLocal($ip);
    }

    protected function isLinkLocal(string $ip): bool
    {
        $packed = inet_pton($ip);
        if ($packed === false) {
            return true;
        }
        if (strlen($packed) === 4) {
            return ord($packed[0]) === 169 && ord($packed[1]) === 254;
        }

        return (ord($packed[0]) === 0xFE && (ord($packed[1]) & 0xC0) === 0x80) || (ord($packed[0]) & 0xFE) === 0xFC;
    }
}
