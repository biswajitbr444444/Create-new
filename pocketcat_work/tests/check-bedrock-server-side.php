<?php
declare(strict_types=1);
function expect_bedrock(bool $ok, string $message): void { if (!$ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } echo "PASS: {$message}\n"; }
$secretEndpoint = file_get_contents(__DIR__ . '/../api/secret.php');
$adminPage = file_get_contents(__DIR__ . '/../admin/ai.php');
$userPage = file_get_contents(__DIR__ . '/../settings/ai.php');
$aiService = file_get_contents(__DIR__ . '/../includes/ai.php');
expect_bedrock(str_contains($secretEndpoint, '$bedrockSecrets') && str_contains($secretEndpoint, 'Saved Bedrock credentials cannot be revealed'), 'secret endpoint blocks browser retrieval of Bedrock credentials');
expect_bedrock(!str_contains($adminPage, 'data-secret-eye') && !str_contains($adminPage, 'data-secret-copy'), 'admin Bedrock settings do not render reveal/copy controls');
expect_bedrock(!str_contains($userPage, 'data-secret-eye') && !str_contains($userPage, 'data-secret-copy'), 'user Bedrock settings do not render reveal/copy controls');
expect_bedrock(str_contains($aiService, 'aws_sign(') && str_contains($aiService, "'Authorization'"), 'AWS signing remains in server-side AI service code');
echo "All Bedrock server-side credential checks passed.\n";
