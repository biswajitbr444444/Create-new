# PocketCat Help & Documentation System

## Purpose
A site-wide documentation layer that gives every supported setting/feature a dedicated `/docs/#feature-key` destination.

## Components
- `docs/index.php` — searchable, responsive documentation center.
- `assets/css/help-docs.css` — documentation layout/theme.
- `assets/js/help-docs.js` — search and active-section navigation.
- `assets/css/help-icon.css` — compact Docs/? control.
- `assets/js/help-icons.js` — automatic label/field discovery plus `data-help` and `data-help-key` support.
- `includes/help.php` — PHP helpers for explicit server-rendered links.

## Integration
For an explicit control:

```php
<label data-help-key="ai-bedrock">Amazon Bedrock API Key
  <input type="password" name="bedrock_api_key">
</label>
```

Or:

```php
<?=help_icon('file-storage')?>
```

For custom components:

```html
<div data-help="visual-editor">...</div>
```

The global help script also maps common existing `name` attributes to documentation keys. This makes legacy forms gain help without rewriting every template, while explicit `data-help-key` remains the preferred method for exact mapping.

## Security
Help links use `target="_blank"` and `rel="noopener"`. Documentation examples use placeholders; real secrets must never be committed to docs or screenshots.
