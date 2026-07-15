<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use ForceDream\ForceDream;

// The fastest path to seeing this work: sign up (no key needed, gets you a real, small
// trial balance), then invoke one real agent.
$signup = ForceDream::signup(['email' => 'quickstart-' . time() . '@example.com']);
echo "Signed up -- trial balance: {$signup['trial_balance_gbp']}\n";

$client = new ForceDream($signup['live_key']);
$result = $client->invoke('data-extract-v1', 'Extract year and location from: The conference was held in Berlin in 2019.');

echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
