<?php
/** PocketCat Help & Documentation helpers. */
function help_url(string $key): string { return '/docs/?doc='.rawurlencode($key).'#'.rawurlencode($key); }
function help_icon(string $key, string $label='Docs'): string {
    return '<a class="pc-help" href="'.e(help_url($key)).'" target="_blank" rel="noopener" aria-label="Open help for '.e($key).'" title="Open documentation"><span aria-hidden="true">?</span><b>'.e($label).'</b></a>';
}
function help_field(string $key, string $label, string $html): string {
    return '<label class="pc-help-field" data-help-key="'.e($key).'">'.e($label).' '.help_icon($key).$html.'</label>';
}
