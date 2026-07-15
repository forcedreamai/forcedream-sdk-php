<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use ForceDream\ForceDream;

// Verification needs no API key either -- ForceDream is never asked whether a proof is
// valid, the signature math decides, locally, in your own process. This is a real task_id
// from a completed, real invocation (proofs are permanent, so this stays verifiable).
$taskId = $argv[1] ?? 'wtask_b13b77ddcf53d4e47fe0';

$client = new ForceDream();
$result = $client->verify(['task_id' => $taskId]);

echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
