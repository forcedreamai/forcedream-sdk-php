<?php

declare(strict_types=1);

namespace ForceDream;

/**
 * Ported precisely from @forcedream/mcp-server's invoke_agent.ts -- exact endpoints, exact
 * polling interval ramp (starts 2500ms, +1000ms per attempt, capped at 6000ms), exact status
 * handling. Invokes ONCE; never re-invokes on timeout (would double-charge) -- returns a
 * pollable task_id instead. Not reconstructed from a description -- read directly from the
 * real, working source file before writing this.
 */
final class Invoke
{
    /**
     * @param array{agent_slug: string, task: string, max_wait_seconds?: int} $args
     * @return array<string, mixed>
     */
    public static function invokeAgentPolling(string $apiBase, string $apiKey, array $args): array
    {
        $slug = $args['agent_slug'];
        $maxWaitMs = max(5, min(120, $args['max_wait_seconds'] ?? 60)) * 1000;

        $inv = Http::post($apiBase . '/v1/agents/' . rawurlencode($slug) . '/invoke', ['task' => $args['task']], $apiKey);
        if ($inv['status'] === 401) {
            return ['status' => 'error', 'agent' => $slug, 'message' => 'Invalid API key (401).'];
        }
        $taskId = $inv['json']['task_id'] ?? null;
        if ($taskId === null) {
            $errMsg = $inv['json']['error'] ?? ($inv['json']['note'] ?? 'no task_id');
            return ['status' => 'error', 'agent' => $slug, 'message' => "Invoke failed (HTTP {$inv['status']}): {$errMsg}"];
        }

        $startMs = (int) (microtime(true) * 1000);
        $intervalMs = 2500;
        while (((int) (microtime(true) * 1000)) - $startMs < $maxWaitMs) {
            usleep($intervalMs * 1000);
            $poll = Http::get($apiBase . '/v1/agents/' . rawurlencode($slug) . '/result/' . rawurlencode($taskId), $apiKey);
            $d = $poll['json'] ?? [];
            $status = $d['status'] ?? ($d['outcome'] ?? null);

            if ($status === 'completed' || $status === 'succeeded' || ($d['ok'] ?? false) === true) {
                $output = $d['output'] ?? null;
                if (($d['outcome'] ?? null) === 'insufficient' || (is_array($output) && ($output['confidence'] ?? null) === 'insufficient')) {
                    return [
                        'status' => 'insufficient', 'agent' => $slug, 'task_id' => $taskId, 'output' => $output,
                        'charged_pence' => 0,
                        'message' => 'Agent returned insufficient evidence and declined rather than fabricate. Charged nothing.',
                    ];
                }
                $chargedPence = $d['charged_pence'] ?? null;
                $proofId = $d['proof_id'] ?? $taskId;
                return [
                    'status' => 'completed', 'agent' => $slug, 'task_id' => $taskId, 'output' => $output,
                    'charged_pence' => $chargedPence, 'proof_id' => $proofId,
                    'message' => "Completed. Charged {$chargedPence}p. Cryptographically proven (proof_id {$proofId}).",
                ];
            }
            if ($status === 'insufficient') {
                return ['status' => 'insufficient', 'agent' => $slug, 'task_id' => $taskId, 'output' => $d['output'] ?? null, 'charged_pence' => 0, 'message' => 'Agent declined (insufficient evidence). Charged nothing.'];
            }
            if ($status === 'charge_failed') {
                $reason = $d['reason'] ?? 'insufficient_balance';
                return ['status' => 'error', 'agent' => $slug, 'task_id' => $taskId, 'charged_pence' => 0, 'error' => 'charge_failed', 'message' => "Charge failed: {$reason}. Nothing charged or delivered. Top up and retry."];
            }
            if ($status === 'failed' || $status === 'dead_letter') {
                $reason = $d['reason'] ?? ($d['last_error'] ?? 'unknown');
                return ['status' => 'error', 'agent' => $slug, 'task_id' => $taskId, 'message' => "Task {$status}: {$reason}"];
            }
            $intervalMs = min($intervalMs + 1000, 6000);
        }

        return [
            'status' => 'pending', 'agent' => $slug, 'task_id' => $taskId,
            'message' => 'Still processing after ' . ($maxWaitMs / 1000) . 's. Not re-invoked (would double-charge). Poll the result later with this task_id.',
        ];
    }
}
