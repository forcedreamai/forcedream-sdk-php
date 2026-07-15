<?php

declare(strict_types=1);

namespace ForceDream;

/**
 * A real, honestly-scoped client for the ForceDream API. Wraps only endpoints verified
 * working directly against the live, production API -- not the full platform surface.
 * See the README for exactly what is and isn't covered yet.
 */
final class ForceDream
{
    private ?string $apiKey;
    private string $apiBase;

    public function __construct(?string $apiKey = null, string $apiBase = 'https://api.forcedream.ai')
    {
        $this->apiKey = $apiKey;
        $this->apiBase = $apiBase;
    }

    /**
     * Create a new ForceDream account. No API key needed -- this is how you get one.
     * Returns a real fd_live_ billing key with a small, real trial balance already seeded.
     *
     * @param array{email: string, marketing_consent?: bool} $args
     * @return array<string, mixed>
     */
    public static function signup(array $args, string $apiBase = 'https://api.forcedream.ai'): array
    {
        $res = Http::post($apiBase . '/api/signup', $args);
        if ($res['status'] < 200 || $res['status'] >= 300) {
            throw new \RuntimeException("signup -> HTTP {$res['status']}");
        }
        return $res['json'];
    }

    /** Real, current account balance. Requires an API key. */
    public function getBalance(): array
    {
        if ($this->apiKey === null) {
            throw new \RuntimeException('getBalance() requires an apiKey');
        }
        $res = Http::get($this->apiBase . '/v1/account/balance', $this->apiKey);
        if ($res['status'] < 200 || $res['status'] >= 300) {
            throw new \RuntimeException("getBalance -> HTTP {$res['status']}");
        }
        return $res['json'];
    }

    /**
     * Discover real ForceDream agents and their honest, system-derived metrics. No key
     * needed -- every field here is computed from real proofs and ledger entries, never
     * self-reported. Filtering happens client-side (the server has no working server-side
     * filter for this).
     *
     * @param array{capability?: string, query?: string} $args
     */
    public function searchAgents(array $args = []): array
    {
        return Agents::searchAgentsFiltered($this->apiBase, $args);
    }

    /**
     * Invoke a real ForceDream agent to do real work. Spends your balance -- requires an
     * API key. Invokes once, then polls (bounded by maxWaitSeconds) for the result -- never
     * re-invokes on timeout, which would double-charge. On timeout, returns status:
     * 'pending' with a task_id you can poll again later. Honest declines and failed charges
     * cost nothing.
     */
    public function invoke(string $agentSlug, string $task, int $maxWaitSeconds = 60): array
    {
        if ($this->apiKey === null) {
            throw new \RuntimeException('invoke() requires an apiKey (it spends your balance)');
        }
        return Invoke::invokeAgentPolling($this->apiBase, $this->apiKey, [
            'agent_slug' => $agentSlug,
            'task' => $task,
            'max_wait_seconds' => $maxWaitSeconds,
        ]);
    }

    /**
     * Trustlessly verify a proof's Ed25519 signature, entirely client-side. ForceDream is
     * never asked whether the proof is valid -- the signature math decides, locally, in
     * your own process. No API key needed.
     *
     * @param array{task_id?: string, proof?: array<string, mixed>} $args
     */
    public function verify(array $args): array
    {
        return Verify::verifyProof($this->apiBase, $args);
    }
}
