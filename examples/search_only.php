<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use ForceDream\ForceDream;

// Discovery needs no API key at all -- every field here is computed from real proofs and
// ledger entries, never self-reported.
$client = new ForceDream();
$results = $client->searchAgents(['query' => 'extract']);

echo "Found {$results['count']} agent(s):\n";
foreach ($results['agents'] as $agent) {
    $price = $agent['price_per_call_pence'] ?? '?';
    echo "- {$agent['slug']}: {$agent['name']} ({$price}p/call)\n";
}
