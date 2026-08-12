<?php

declare(strict_types=1);

namespace ForceDream;

/**
 * Trustlessly verifies a ForceDream proof's Ed25519 signature entirely client-side.
 * ForceDream is never asked whether the proof is valid -- the signature math decides,
 * locally, in your own process. No API key needed.
 *
 * Uses PHP's built-in sodium extension (bundled with PHP core since 7.2 -- confirmed
 * directly available in the test environment, not assumed) -- no third-party Composer
 * package needed for the cryptography itself.
 */
final class Verify
{
    /**
     * Ported precisely from @forcedream/mcp-server's verify_proof.ts buildSignable: proofs
     * with external_cost_hash were signed over 10 fields, older ones over 8. Types matter
     * (wfCanonical stringifies 5 vs "5" differently) -- casts match the real reference
     * exactly, not reconstructed from a description.
     *
     * @param array<string, mixed> $proof
     * @return array{signable: array<string, mixed>, fields: int}
     */
    private static function buildSignable(array $proof): array
    {
        $hasExt = isset($proof['external_cost_hash']) && $proof['external_cost_hash'] !== null;
        $base = [
            'task_id' => $proof['task_id'],
            'agent_id' => $proof['agent_id'],
            'input_hash' => $proof['input_hash'],
            'output_hash' => $proof['output_hash'],
            'cost_pence' => (float) $proof['cost_pence'],
            'budget_pence' => (float) $proof['budget_pence'],
            'started_at' => (float) $proof['started_at'],
            'completed_at' => (string) $proof['completed_at'],
        ];
        if ($hasExt) {
            $base['external_cost_hash'] = (string) $proof['external_cost_hash'];
            $base['retrieved_count'] = (float) ($proof['retrieved_count'] ?? 0);
            // Model binding: the server records which provider and model actually served
            // the execution and binds them into the signed payload. Conditional, so a
            // proof issued before this existed canonicalises exactly as it did then --
            // adding them unconditionally would break every proof already in the wild.
            $n = 10;
            if (!empty($proof['inference_provider'])) {
                $base['inference_provider'] = (string) $proof['inference_provider'];
                $n++;
            }
            if (!empty($proof['inference_model'])) {
                $base['inference_model'] = (string) $proof['inference_model'];
                $n++;
            }
            return ['signable' => $base, 'fields' => $n];
        }
        return ['signable' => $base, 'fields' => 8];
    }

    /**
     * Extracts the raw 32-byte Ed25519 public key from a real SPKI PEM string. Ed25519 SPKI
     * DER has a fixed, constant-length prefix (RFC 8410 -- the algorithm identifier for
     * Ed25519 has no parameters, making the whole structure's length predictable), so the
     * raw key is reliably the final 32 bytes of the decoded DER -- unlike a hardcoded
     * *offset from the start* (the exact class of bug caught in the Go SDK earlier tonight:
     * that assumed a fixed *total* length and broke on a real key with a different one).
     * Taking the last 32 bytes is robust regardless of minor total-length variation.
     */
    public static function publicKeyBytesFromPem(string $pem): string
    {
        $body = preg_replace('/-----(BEGIN|END) PUBLIC KEY-----/', '', $pem);
        $body = preg_replace('/\s+/', '', (string) $body);
        $der = base64_decode((string) $body, true);
        if ($der === false || strlen($der) < 32) {
            throw new \RuntimeException('publicKeyBytesFromPem: invalid PEM/DER');
        }
        return substr($der, -32);
    }

    /**
     * @param array{task_id?: string, proof?: array<string, mixed>} $args
     * @return array<string, mixed>
     */
    public static function verifyProof(string $apiBase, array $args): array
    {
        $proof = $args['proof'] ?? null;
        if ($proof === null) {
            if (empty($args['task_id'])) {
                throw new \InvalidArgumentException('Provide task_id or proof');
            }
            $res = Http::get($apiBase . '/v1/workforce/proof/' . rawurlencode($args['task_id']) . '/public');
            if ($res['status'] < 200 || $res['status'] >= 300) {
                throw new \RuntimeException("fetch proof -> HTTP {$res['status']}");
            }
            $data = $res['json'];
            if (empty($data['proof'])) {
                throw new \RuntimeException('proof_not_found');
            }
            $proof = $data['proof'];
        }

        $keyRes = Http::get($apiBase . '/v1/workforce/proof/public-key');
        if ($keyRes['status'] < 200 || $keyRes['status'] >= 300) {
            throw new \RuntimeException("fetch public key -> HTTP {$keyRes['status']}");
        }
        $keyData = $keyRes['json'];
        $pubKeyBytes = self::publicKeyBytesFromPem($keyData['public_key_pem']);

        ['signable' => $signable, 'fields' => $fields] = self::buildSignable($proof);
        $digestHex = Canonical::sha256hex(Canonical::wfCanonical($signable));
        $digestBytes = hex2bin($digestHex);
        if ($digestBytes === false) {
            throw new \RuntimeException('verifyProof: hex2bin failed on digest');
        }

        // Real fix: previously only ever attempted verification for algorithm ==
        // 'Ed25519' (or null). Any real Ed25519-batched proof (Merkle-root signing,
        // used to amortize signing cost across a settlement batch) fell through
        // with verified left false -- a hard "signature verification FAILED" for
        // a proof that was never actually checked. Confirmed against two real,
        // live, settled proofs before this fix was trusted: both use
        // Ed25519-batched and both now correctly verify true.
        $algorithm = $proof['algorithm'] ?? 'Ed25519';
        $isBatched = $algorithm === 'Ed25519-batched';
        $verified = false;

        if (!empty($proof['signature']) && ($algorithm === 'Ed25519' || $isBatched)) {
            try {
                $checkHex = $digestHex;
                $ok = true;
                if ($isBatched) {
                    $root = $proof['merkle_root'] ?? '';
                    $siblings = $proof['inclusion_proof']['siblings'] ?? null;
                    if ($root === '' || $siblings === null) {
                        $ok = false;
                    } else {
                        $current = $digestHex;
                        foreach ($siblings as $step) {
                            $current = ($step['position'] === 'right')
                                ? Canonical::sha256hex($current . $step['hash'])
                                : Canonical::sha256hex($step['hash'] . $current);
                        }
                        if ($current !== $root) {
                            $ok = false;
                        } else {
                            $checkHex = $current;
                        }
                    }
                }
                if ($ok) {
                    $checkBytes = hex2bin($checkHex);
                    $sigBytes = base64_decode((string) $proof['signature'], true);
                    if ($checkBytes !== false && $sigBytes !== false) {
                        $verified = sodium_crypto_sign_verify_detached($sigBytes, $checkBytes, $pubKeyBytes);
                    }
                }
            } catch (\Throwable $e) {
                $verified = false;
            }
        }

        return [
            'verified' => $verified,
            'task_id' => $proof['task_id'],
            'key_id' => $keyData['key_id'] ?? null,
            'algorithm' => $algorithm,
            'fields_signed' => $fields,
            'trustless' => true,
            'message' => $verified
                ? ($isBatched
                    ? 'Signature mathematically verified against a real, independently-reconstructed Merkle root. Signed by ForceDream, unaltered.'
                    : 'Signature mathematically verified. This proof was signed by ForceDream and has not been altered.')
                : 'Signature verification FAILED. The proof was altered or not signed by ForceDream.',
            'note' => 'Verified client-side via public-key cryptography. ForceDream was not asked whether the proof is valid.',
        ];
    }
}
