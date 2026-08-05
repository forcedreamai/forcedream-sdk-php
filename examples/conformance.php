<?php
// Runs the shared cross-SDK conformance suite against a local mock server.
// Start forcedream-sdk-conformance/harness/mock_server.py first.

require_once __DIR__ . '/../vendor/autoload.php';

use ForceDream\ForceDream;

$cases = [
    'conf_a_real_batched' => true,
    'conf_b_real_batched' => true,
    'conf_c_bad_signature' => false,
    'conf_d_bad_payload' => false,
    'conf_e_bad_algorithm' => false,
    'conf_f_siblings_wrong_root' => false,
    'conf_g_missing_root' => false,
];

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
