<?php
/*
 * PocketCat V11.6 — current InfinityFree hosting defaults.
 * Sensitive values are intentionally blank and must be entered in the
 * first-run installer or the admin settings.
 */
return [
    'db' => [
        'host' => 'sql104.infinityfree.com',
        'port' => '3306',
        'name' => 'if0_42811059_pocketcat_online',
        'user' => 'if0_42811059',
        'password' => '',
    ],
    'admin' => [
        'name' => '',
        'email' => '',
        'username' => 'admin',
        'password' => '',
    ],
    'services' => [
        'bedrock_api_key' => '',
        'bedrock_endpoint_url' => 'https://bedrock-mantle.us-east-1.api.aws/v1',
        'aws_region' => 'us-east-1',
        'bedrock_model_id' => 'openai.gpt-oss-120b',
        'bedrock_api_mode' => 'responses',
        'bedrock_transport' => 'relay',
        'bedrock_relay_url' => 'https://orange-lake-6872.biswajitw111111.workers.dev',
        'bedrock_relay_token' => '',
        'aws_access_key_id' => '',
        'aws_secret_access_key' => '',
        'aws_session_token' => '',
        'smtp_host' => '',
        'smtp_port' => '587',
        'smtp_username' => '',
        'smtp_password' => '',
        'smtp_from_email' => 'support@pocketcat.online',
        'smtp_from_name' => 'PocketCat',
        'app_name' => 'PocketCat',
        'app_url' => 'https://pocketcat.online',
    ],
];
