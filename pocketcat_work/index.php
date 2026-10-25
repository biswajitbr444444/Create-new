<?php
// Clean the stray homepage-only `?i=1` parameter without disturbing legitimate
// query parameters (for example, analytics campaign tags).
$requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
$requestPath = parse_url($requestUri, PHP_URL_PATH) ?: '/';
$requestQuery = parse_url($requestUri, PHP_URL_QUERY);
if ($requestPath === '/' && is_string($requestQuery) && $requestQuery !== '') {
    $queryParts = explode('&', $requestQuery);
    $keptParts = [];
    $removedIndexFlag = false;
    foreach ($queryParts as $queryPart) {
        if ($queryPart === '') {
            continue;
        }
        $pair = explode('=', $queryPart, 2);
        $key = rawurldecode($pair[0]);
        $value = rawurldecode($pair[1] ?? '');
        if ($key === 'i' && $value === '1') {
            $removedIndexFlag = true;
            continue;
        }
        $keptParts[] = $queryPart;
    }
    if ($removedIndexFlag && !headers_sent()) {
        $target = '/'.($keptParts ? '?'.implode('&', $keptParts) : '');
        header('Cache-Control: no-store');
        header('Location: '.$target, true, 302);
        exit;
    }
}
require __DIR__.'/bootstrap.php';
plan_catalog_no_cache_headers(); public_header('PocketCat'); $stats=platform_stats(); $logged=current_user();
?>
<div class="home-shell">
<section class="home-hero">
  <div class="home-hero-copy">
    <span class="eyebrow">POCKETCAT · WORKSPACE FOR FOCUSED TEAMS</span>
    <h1>Everything important to your work, in one calm place.</h1>
    <p>Plan tasks, capture notes, organize projects, manage files and use AI when it genuinely saves time.</p>
    <div class="hero-actions"><a class="btn" href="<?= $logged?'/dashboard/':'/signup/' ?>"><?= $logged?'Open workspace':'Create free account' ?></a><a class="btn secondary" href="/product/">Explore PocketCat</a></div>
    <div class="hero-trust"><span>✓ Secure account access</span><span>✓ Responsive on mobile</span><span>✓ Optional AI</span></div>
  </div>
  <div class="home-hero-visual" aria-label="PocketCat workspace preview">
    <div class="workspace-window">
      <div class="window-bar"><span class="window-title"><b>P</b> PocketCat</span><span class="window-status">Workspace</span></div>
      <div class="workspace-grid">
        <aside><small>WORKSPACE</small><strong>Today</strong><span>Tasks</span><span>Notes</span><span>Projects</span><span>Files</span></aside>
        <div class="workspace-main"><div class="workspace-heading"><div><small>GOOD AFTERNOON</small><h3>Today</h3></div><span class="workspace-chip">3 priorities</span></div><div class="preview-task"><span class="preview-check"></span><div><b>Finish launch plan</b><small>Due today · Marketing</small></div><em>High</em></div><div class="preview-task"><span class="preview-check done">✓</span><div><b>Review product notes</b><small>Completed · Product</small></div><em>Done</em></div><div class="preview-task"><span class="preview-check"></span><div><b>Prepare team update</b><small>Tomorrow · Operations</small></div><em>Medium</em></div><div class="preview-note"><small>AI ASSISTANCE</small><b>Turn notes into a clear action list.</b><span>Optional · You stay in control</span></div></div>
      </div>
    </div>
  </div>
</section>

<section class="home-section home-services">
  <div class="home-section-head"><div><span class="eyebrow">START HERE</span><h2>One workspace. Clear next steps.</h2></div><p>Open the part of PocketCat you need without navigating through clutter.</p></div>
  <div class="service-grid">
    <a class="service-card" href="/tasks/"><span class="service-icon">✓</span><div><h3>Tasks</h3><p>Priorities, deadlines, status and project context.</p></div><span class="service-arrow">→</span></a>
    <a class="service-card" href="/notes/"><span class="service-icon">N</span><div><h3>Notes</h3><p>Keep decisions, research and ideas close to the work.</p></div><span class="service-arrow">→</span></a>
    <a class="service-card" href="/projects/"><span class="service-icon">P</span><div><h3>Projects</h3><p>Group work, milestones and outcomes in one view.</p></div><span class="service-arrow">→</span></a>
    <a class="service-card" href="/files/"><span class="service-icon">↗</span><div><h3>Files & sharing</h3><p>Store, share and find important project files.</p></div><span class="service-arrow">→</span></a>
  </div>
</section>

<section class="home-section home-stats" data-live-workspace-section="homepage" <?=empty($stats['show_homepage_section'])?'hidden':''?>>
  <div class="home-section-head"><div><span class="eyebrow">LIVE WORKSPACE</span><h2>Built to stay useful as work grows.</h2></div><p>These five counters use administrator-configured display values. They may be used for demonstrations and are not audited business metrics. The growth chart below shows sample projections, not verified platform activity.</p></div>
  <div class="stats-strip"><div><strong data-platform-stat="registered_users"><?=number_format($stats['registered_users'])?></strong><span>Registered users</span></div><div><strong data-platform-stat="active_users"><?=number_format($stats['active_users'])?></strong><span>Active recently</span></div><div><strong data-platform-stat="tasks"><?=number_format($stats['tasks'])?></strong><span>Tasks created</span></div><div><strong data-platform-stat="notes"><?=number_format($stats['notes'])?></strong><span>Notes created</span></div><div><strong data-platform-stat="projects"><?=number_format($stats['projects'])?></strong><span>Projects created</span></div></div>
</section>

<?php $growthConfig=demo_growth_config(); $growthSeries=demo_growth_series(); $growthMax=max(1,max(array_column($growthSeries,'value'))); ?>
<?php if($growthConfig['show_chart']): ?>
<section class="home-section home-growth-chart" aria-labelledby="public-growth-title">
  <div class="home-section-head"><div><span class="eyebrow">PLATFORM OUTLOOK</span><h2 id="public-growth-title"><?=e($growthConfig['title'])?></h2></div><p><?=e($growthConfig['description'])?></p></div>
  <div class="card growth-chart-card" data-growth-chart><div class="page-head"><div><h3 data-growth-title><?=e($growthConfig['title'])?></h3><p class="muted" data-growth-description><?=e($growthConfig['description'])?></p></div></div><div class="chart-wrap large-chart" data-growth-bars><?php foreach($growthSeries as $m):$h=max(8,round($m['value']/$growthMax*100));?><div class="chart-col"><span class="chart-value"><?=number_format($m['value'])?></span><div class="bar" style="height:<?=$h?>%"></div><span class="chart-label"><?=e($m['label'])?></span></div><?php endforeach;?></div></div>
</section>
<?php endif; ?>

<section class="home-section home-feature-band">
  <div class="home-section-head"><div><span class="eyebrow">OPTIONAL INTELLIGENCE</span><h2>AI that assists. You decide.</h2></div><p>Use Amazon Bedrock-powered assistance for planning, review and transformation without putting AI in charge of your workspace.</p></div>
  <div class="trust-grid"><article><span>01</span><h3>Ask</h3><p>Give AI a clear prompt using the context you choose.</p></article><article><span>02</span><h3>Review</h3><p>Check suggestions before they become actions.</p></article><article><span>03</span><h3>Act</h3><p>Turn useful output into tasks and decisions you control.</p></article></div>
</section>

<section class="home-section home-plans">
  <div class="home-section-head"><div><span class="eyebrow">PLANS</span><h2>Start free. Upgrade when you need more.</h2></div><p>Simple tiers with clear workspace and AI allowances.</p></div>
  <?php $planCatalog=plan_catalog(); ?><div class="plan-grid home-plan-grid"><?php foreach(['free','basic','premium'] as $planCardKey): if(empty($planCatalog['plan_meta'][$planCardKey]['enabled'])) continue; $planCardContext='homepage'; require __DIR__.'/includes/plan-card.php'; endforeach; ?></div>
</section>

<section class="home-cta"><div><span class="eyebrow">GET STARTED</span><h2>Make your work easier to find, finish and share.</h2><p>Start with the essentials. Add AI and advanced tools only when they help.</p></div><a class="btn" href="<?= $logged?'/dashboard/':'/signup/' ?>"><?= $logged?'Open workspace':'Create free account' ?></a></section>
</div>
<div class="ai-fab" data-ai-widget><button type="button" class="ai-fab-button" aria-label="Open AI Assistant">✦</button><div class="ai-panel"><div class="ai-panel-head"><div><strong>PocketCat AI</strong><small>Optional assistant</small></div><button type="button" class="ai-close" data-ai-close>×</button></div><div class="ai-messages" data-ai-messages><p class="muted">Ask a quick question about planning, writing or your work.</p></div><form data-ai-form><textarea name="prompt" rows="3" placeholder="Ask PocketCat AI…" required></textarea><button class="btn full">Send</button></form></div></div>
<script src="/assets/js/platform-stats.js?v=19.72" defer></script>
<script src="/assets/js/growth-chart.js?v=19.72" defer></script>
<?php public_footer(); ?>
