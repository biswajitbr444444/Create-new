<?php
$contentEditor = file_get_contents(__DIR__.'/../admin/content-edit.php');
$cmsScript = file_get_contents(__DIR__.'/../assets/js/cms-editor.js');
$files = file_get_contents(__DIR__.'/../includes/files.php');
$aiChat = file_get_contents(__DIR__.'/../api/ai-chat.php');
$comments = file_get_contents(__DIR__.'/../admin/comments.php');
$cmsAdminScript = file_get_contents(__DIR__.'/../assets/js/cms-admin.js');
$cloudStorage = file_get_contents(__DIR__.'/../includes/cloud-storage.php');
$links = file_get_contents(__DIR__.'/../includes/links.php');
$integrationMocks = file_get_contents(__DIR__.'/run-local-integration-mocks.php');
$filesApi = file_get_contents(__DIR__.'/../api/files.php');
$sharePage = file_get_contents(__DIR__.'/../file/share/index.php');
$billing = file_get_contents(__DIR__.'/../includes/billing.php');
$outerStart = strpos($contentEditor, '<form method="post" class="cms-editor-layout"');
$outerEnd = $outerStart === false ? false : strpos($contentEditor, '</aside></form>', $outerStart);
$outerForm = $outerStart === false || $outerEnd === false ? '' : substr($contentEditor, $outerStart, $outerEnd - $outerStart);
$checks = [
    'CMS editor has no nested forms that detach edit controls' => substr_count($outerForm, '<form') === 1,
    'CMS revision restoration uses a separate form and explicit revision id' => strpos($contentEditor, 'id="cmsRestoreRevisionForm"') !== false && strpos($contentEditor, 'data-restore-revision="<?=$rv[\'id\']?>"') !== false && strpos($cmsScript, 'restoreForm.requestSubmit()') !== false,
    'local uploaded objects receive owner/group-only file permissions' => strpos($files, "chmod(\$localPath,0640)") !== false && strpos($files, 'Could not secure uploaded file permissions.') !== false,
    'AI chat retrieves inserted message ids through the shared database helper' => strpos($aiChat, '$userMessageId=(int)db()->lastInsertId()') !== false && strpos($aiChat, '$db->lastInsertId()') === false,
    'external integration mock transports are explicit and CLI-only' => strpos($cloudStorage, 'Mock cloud transport is available only to CLI tests.') !== false && strpos($links, 'Mock webhook transport is available only to CLI tests.') !== false && strpos($integrationMocks, 'cloud_oauth_callback($provider') !== false && strpos($integrationMocks, 'link_webhook_event($linkId') !== false,
    'single-comment moderation resolves the row action over the bulk form action' => strpos($comments, "if(\$action==='bulk'&&\$id)\$action='approved';") !== false,
    'CMS list actions submit selected and single-item mutations' => strpos($cmsAdminScript, "qsa('[data-cms-action]')") !== false && strpos($cmsAdminScript, "qsa('[data-single-action]')") !== false && strpos($cmsAdminScript, 'bulkForm.requestSubmit()') !== false,
    'file password protection and archive APIs enforce per-plan entitlements' => strpos($filesApi, "policy_allows(\$uid,'file_password',false))file_json") !== false && strpos($filesApi, "policy_allows((int)\$u['id'],'file_zip_downloads',false))file_json") !== false && strpos($filesApi, "policy_allows((int)\$share['user_id'],'file_zip_downloads',false))file_json") !== false && strpos($sharePage, "policy_allows((int)\$share['user_id'],'file_zip_downloads',false)") !== false,
    'ZIP archives fall back to a system zip utility when PHP ZipArchive is unavailable' => strpos($filesApi, "command -v zip 2>/dev/null") !== false && strpos($filesApi, "\$run([\$zip,'-q','-r',\$out,'.']") !== false,
    'billing checkout sandbox is opt-in and limited to local environment' => strpos($billing, "strtolower((string)getenv('APP_ENV'))==='local'") !== false && strpos($billing, "strtolower((string)getenv('BILLING_SANDBOX'))==='true'") !== false,
];
$failed = false;
foreach ($checks as $label => $ok) {
    echo ($ok ? 'PASS: ' : 'FAIL: ').$label."\n";
    if (!$ok) $failed = true;
}
if ($failed) exit(1);
echo "All project QA regression checks passed.\n";
