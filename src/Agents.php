<?php

declare(strict_types=1);

namespace ForceDream;

/**
 * Ported precisely from @forcedream/mcp-server's search_agents.ts. Real, load-bearing fact
 * confirmed directly from that source, not assumed: the server has no working server-side
 * capability/query filter on /v1/agents/list -- filtering must happen client-side, after
 * fetching the full list. Also merges in real reliability data from the separate
 * /v1/agents/reliability endpoint, exactly as the proven implementation does.
 */
final class Agents
{
    /**
     * @param array{capability?: string, query?: string} $args
     * @return array{count: int, agents: array<int, array<string, mixed>>, note: string}
     */
    public static function searchAgentsFiltered(string $apiBase, array $args = []): array
    {
        $listRes = Http::get($apiBase . '/v1/agents/list');
        if ($listRes['status'] < 200 || $listRes['status'] >= 300) {
            throw new \RuntimeException("fetch {$apiBase}/v1/agents/list -> HTTP {$listRes['status']}");
        }
        $data = $listRes['json'];
        $agents = is_array($data['agents'] ?? null) ? $data['agents'] : [];

        $reliabilityBySlug = [];
        try {
            $relRes = Http::get($apiBase . '/v1/agents/reliability');
            if ($relRes['status'] >= 200 && $relRes['status'] < 300 && is_array($relRes['json']['agents'] ?? null)) {
                foreach ($relRes['json']['agents'] as $ra) {
                    if (!empty($ra['agent_slug'])) {
                        $reliabilityBySlug[$ra['agent_slug']] = $ra['reliability'] ?? null;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Matches the reference's .catch(() => null) -- reliability enrichment is
            // best-effort; its absence must not break the core search result.
        }

        if (!empty($args['capability'])) {
            $cap = strtolower($args['capability']);
            $agents = array_values(array_filter($agents, function ($a) use ($cap) {
                $caps = $a['capabilities'] ?? [];
                foreach ($caps as $c) {
                    if (strtolower((string) $c) === $cap) {
                        return true;
                    }
                }
                return false;
            }));
        }
        if (!empty($args['query'])) {
            $q = strtolower($args['query']);
            $agents = array_values(array_filter($agents, function ($a) use ($q) {
                if (str_contains(strtolower($a['slug'] ?? ''), $q)) {
                    return true;
                }
                if (str_contains(strtolower($a['name'] ?? ''), $q)) {
                    return true;
                }
                foreach ($a['capabilities'] ?? [] as $c) {
                    if (str_contains(strtolower((string) $c), $q)) {
                        return true;
                    }
                }
                return false;
            }));
        }

        $enriched = array_map(function ($a) use ($reliabilityBySlug) {
            $a['health'] = $reliabilityBySlug[$a['slug']] ?? null;
            return $a;
        }, $agents);

        return [
            'count' => count($enriched),
            'agents' => $enriched,
            'note' => count($enriched) === 0
                ? 'No agents matched. The registry contains only real, registered agents with cryptographic proofs.'
                : 'Metrics are system-derived from proofs/ledger (proof_count, success_rate) -- never self-reported. Health (success_rate, avg_latency_ms, sample_size) is honestly null where no real reliability data exists yet.',
        ];
    }
}
