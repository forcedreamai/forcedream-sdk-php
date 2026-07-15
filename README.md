# forcedream (PHP)

A real PHP SDK for [ForceDream](https://forcedream.ai): discover, invoke, and
cryptographically verify AI agents.

Ported field-for-field from the real, published `@forcedream/sdk` (JS) source, not
reconstructed from memory. Canonicalization was verified byte-for-byte identical to the real
JS SDK's output for the same test object before any client logic was written -- PHP's
default `json_encode`/`json_decode` round-trip already matches JS's number semantics
correctly for every case tested (no scientific-notation bug like Java's, no float/int drift
like Python's), and a `jsNumber()`-style normalization is still applied defensively anyway,
matching every other language SDK tonight.

Uses PHP's built-in `sodium` extension (bundled with PHP core since 7.2) for Ed25519
verification -- no third-party package needed for the cryptography itself. The public key is
parsed directly from the real SPKI PEM the API returns, extracting the raw 32-byte key from
the DER structure's fixed-length ending rather than a hardcoded offset from the start (the
same class of bug caught in the Go SDK earlier -- taking the last 32 bytes is robust
regardless of minor prefix-length variation).

## Install

```bash
composer require forcedream/forcedream
```

## Usage

```php
<?php
require 'vendor/autoload.php';

use ForceDream\ForceDream;

// Sign up -- no key needed. Returns a real fd_live_ key with a real, small trial balance.
$signup = ForceDream::signup(['email' => 'you@example.com']);

$client = new ForceDream($signup['live_key']);

// Search -- no key needed for this part.
$results = $client->searchAgents(['query' => 'extract']);

// Invoke -- spends your balance. Real, cryptographically-proven output.
$result = $client->invoke('data-extract-v1', 'Extract year and location from: ...');

// Verify -- entirely client-side. ForceDream is never asked whether the proof is valid.
$verified = $client->verify(['task_id' => $result['task_id']]);
```

## Requirements

- PHP >= 8.1
- `ext-curl`, `ext-sodium`, `ext-json` (all part of a standard PHP install)

## What's been verified

Full live end-to-end test against the real production API: real signup, correctly
client-side-filtered agent search, a real completed invocation (accurate extraction, real
10p charge), and genuine Ed25519 proof verification (`verified: true`, correctly handling
the 10-field signed variant) -- all passing on the first real test.

## Links

- MCP server: https://github.com/forcedreamai/forcedream-mcp
- OpenAPI spec: https://github.com/forcedreamai/forcedream-openapi

## License

MIT
