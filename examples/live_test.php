<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use ForceDream\ForceDream;

echo "=== Real signup ===\n";
$signup = ForceDream::signup(['email' => 'php-sdk-test-' . time() . '@example.com', 'marketing_consent' => false]);
echo "Signed up: user_id={$signup['user_id']}, trial_balance={$signup['trial_balance_gbp']}\n\n";

$client = new ForceDream($signup['live_key']);

echo "=== searchAgents (client-side filtered) ===\n";
$results = $client->searchAgents(['query' => 'extract']);
echo json_encode($results, JSON_PRETTY_PRINT) . "\n\n";

echo "=== invoke (real agent, real charge) ===\n";
$invokeResult = $client->invoke('data-extract-v1', 'Extract year and location from: In 2015, the summit was held in Seoul, South Korea.', 60);
echo json_encode($invokeResult, JSON_PRETTY_PRINT) . "\n\n";

echo "=== verify (real Ed25519 proof) ===\n";
$taskId = $invokeResult['task_id'] ?? null;
if ($taskId !== null) {
    $verifyResult = $client->verify(['task_id' => $taskId]);
    echo json_encode($verifyResult, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "No task_id to verify.\n";
}
