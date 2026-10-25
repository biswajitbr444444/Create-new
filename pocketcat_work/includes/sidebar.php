<div class="sidebar-backdrop" data-sidebar-close></div>
<aside class="sidebar" id="app-sidebar">
  <div class="sidebar-label">WORKSPACE</div>
  <nav>
    <a href="/dashboard/">Dashboard</a>
    <a href="/tasks/"><?=e(ui_label("tasks","Tasks"))?></a>
    <a href="/notes/"><?=e(ui_label("notes","Notes"))?></a>
    <a href="/projects/"><?=e(ui_label("projects","Projects"))?></a>
    <a href="/links/">Links & Analytics</a><a href="/analytics/">Advanced Analytics</a>
    <a href="/files/">File Storage & Sharing</a>
    <a href="/media/">Media Library</a>
    <a href="/code/">Code Editor</a>
    <a href="/ai/"><?=e(ui_label("ai_assistant","AI Assistant"))?></a>
    <a href="/search/"><?=e(ui_label("search","Search"))?></a>
    <a href="/settings/">My Settings</a><a href="/settings/link-api/">Developer API</a><a href="/settings/ai/">AWS Bedrock</a><a href="/billing/">Plans & Billing</a><a href="/support/">Support Chat</a><a href="/docs/">Help & Documentation</a>
    <div class="sidebar-label utility-label">QUICK ACCESS</div><a href="/docs/">Help & Documentation</a><a href="/" target="_blank" rel="noopener">View site ↗</a>
  </nav>
  <?php if((current_user()['role']??'')==='admin' && is_admin_view()): ?>
    <div class="sidebar-label admin-label">ADMINISTRATION</div>
    <nav>
      <a href="/admin/">Admin Dashboard</a><a href="/admin/ota-updater/">OTA Update Center</a>
      <div class="sidebar-label utility-label">CONTENT</div>
      <a href="/admin/posts/">Posts</a><a href="/admin/pages/">Pages</a><a href="/admin/categories/">Categories</a><a href="/admin/tags/">Tags</a><a href="/admin/comments/">Comments</a><a href="/admin/version-details/">Version Details</a>
      <a href="/admin/users/">Users</a><a href="/admin/user-create/">Create User</a>
      <a href="/admin/database/">Database</a>
      <a href="/admin/email/">Email & SMTP</a>
      <a href="/admin/ai/">AWS Bedrock</a><a href="/admin/ai-users/">Customer AI Access</a>
      <a href="/admin/settings/">Site Settings</a><a href="/admin/geo-providers/">Local GeoIP</a>
      <a href="/admin/site-editor/">Live Site Editor</a><a href="/admin/platform-activity/">Platform Activity</a><a href="/admin/analytics/">Advanced Analytics</a>
      <a href="/admin/docs/">Documentation CMS</a>
      <a href="/admin/security/">Security</a><a href="/admin/files/">File Moderation</a>
      <a href="/admin/messages/">Contact Messages</a><a href="/admin/billing/">Billing Requests</a><a href="/admin/support/">Support Chat</a>
    </nav>
  <?php endif; ?>
</aside>