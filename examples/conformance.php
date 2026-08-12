<?php
// Runs the shared cross-SDK conformance suite against a local mock server.
// Start forcedream-sdk-conformance/harness/mock_server.py first.

require_once __DIR__ . '/../vendor/autoload.php';

use ForceDream\ForceDream;

// Cases come from the server, never a literal here. A hardcoded list is a snapshot
// that silently drifts: when the contract gained conf_h and conf_i, every hardcoded
// harness kept running seven cases and reporting green.
$raw = @file_get_contents('http://127.0.0.1:8787/conformance/cases');
if ($raw === false) {
    fwrite(STDERR, "Could not fetch the contract. Start harness/mock_server.py first.\n");
    exit(2);
}
$cases = array_map(fn($m) => $m['expected'], json_decode($raw, true) ?: []);
if (count($cases) === 0) {
    fwrite(STDERR, "INCONCLUSIVE: the server returned no cases.\n");
    exit(2);
}

$fd = new ForceDream(null, 'http://127.0.0.1:8787');
$passed = 0; $failed = 0; $errored = 0;

foreach ($cases as $id => $expected) {
    try {
        $r = $fd->verify(['task_id' => $id]);
        $actual = $r['verified'];
        if ($actual === $expected) {
            printf("  PASS  %-32s verified=%s\n", $id, var_export($actual, true));
            $passed++;
        } else {
            printf("  FAIL  %-32s expected=%s got=%s\n", $id, var_export($expected, true), var_export($actual, true));
            $failed++;
        }
    } catch (\Throwable $e) {
        printf("  ERROR %-32s %s: %s\n", $id, get_class($e), $e->getMessage());
        $errored++;
    }
}

printf("\n%d/%d passed, %d failed, %d threw\n", $passed, count($cases), $failed, $errored);
exit($failed === 0 && $errored === 0 ? 0 : 1);
