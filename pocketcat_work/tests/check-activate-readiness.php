<?php
declare(strict_types=1);
function expect_activate(bool $ok, string $message): void {
    if (!$ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
    echo "PASS: {$message}\n";
}
$root = dirname(__DIR__);
$home = file_get_contents($root . '/index.php');
$about = file_get_contents($root . '/about/index.php');
$how = file_get_contents($root . '/how-it-works/index.php');
$dashboard = file_get_contents($root . '/dashboard/index.php');
$functions = file_get_contents($root . '/includes/functions.php');
$siteEditor = file_get_contents($root . '/admin/site-editor.php');
$settings = file_get_contents($root . '/settings/index.php');
$routes = file_get_contents($root . '/.htaccess');
$signup = file_get_contents($root . '/auth/signup.php');
$genericRepeatedCopy = 'In practice, this principle affects the details of the product. Users should be able to move from the public website to sign up';
expect_activate(substr_count($about, 'class="about-section"') === 20, 'About page retains all 20 numbered sections');
expect_activate(!str_contains($about, $genericRepeatedCopy), 'About page excludes the repeated generic paragraph');
expect_activate(str_contains($how, 'deployment operator provisions a PHP-compatible hosting environment'), 'How It Works accurately describes deployment-time setup');
expect_activate(str_contains($home, 'not audited business metrics') && str_contains($dashboard, 'not audited business metrics'), 'homepage and dashboard label configured counters honestly');
expect_activate(str_contains($functions, "'show_chart'=>setting('show_demo_growth_chart','1')==='1'") && str_contains($siteEditor, 'Demo Data ON/OFF') && str_contains($siteEditor, 'show_demo_growth_chart') && !str_contains($home, '>DEMO DATA</span>') && !str_contains($dashboard, '>DEMO DATA</span>') && !str_contains(file_get_contents($root . '/admin/index.php'), '>DEMO DATA</span>'), 'admin can toggle the illustrative chart and DEMO DATA badge text is removed from rendered charts');
expect_activate(str_contains($home, "\$growthConfig['show_chart']") && str_contains($dashboard, "\$growthConfig['show_chart']"), 'demo chart toggle applies to both public homepage and user dashboard');
expect_activate(str_contains($settings, 'href="/settings/ai/"') && str_contains($settings, 'server-side'), 'account settings links to server-side Amazon Bedrock configuration');
expect_activate(str_contains($home, "\$key === 'i' && \$value === '1'") && str_contains($home, 'header(\'Location: \'.$target, true, 302)'), 'homepage removes the stray ?i=1 query parameter');
expect_activate(str_contains($routes, 'RewriteRule ^signup/?$ auth/signup.php [L,QSA]') && str_contains($signup, 'Create Account'), 'public signup route maps to the account creation flow');
$policyFiles = ['/privacy/index.php','/terms/index.php','/cookie-policy/index.php','/acceptable-use/index.php','/security/index.php','/contact/index.php'];
foreach ($policyFiles as $file) {
    $content = file_get_contents($root . $file);
    expect_activate(str_contains($content, 'support@pocketcat.online'), basename(dirname($file)) . ' page uses the standard support address');
}
echo "All AWS Activate readiness checks passed.\n";
