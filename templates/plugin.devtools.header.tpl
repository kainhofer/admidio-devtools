<div id="adm_plg_devtools_header" class="d-none d-flex align-items-center me-2 admidio-devtools-header">
    {if $devtoolsUrl}
        <a class="badge text-bg-warning text-decoration-none me-2" href="{$devtoolsUrl}" title="{$l10n->get('PLG_DEVTOOLS_BADGE_DESC')}"><i class="bi bi-tools"></i> {$l10n->get('PLG_DEVTOOLS_BADGE')}</a>
    {else}
        <span class="badge text-bg-warning me-2" title="{$l10n->get('PLG_DEVTOOLS_BADGE_DESC')}"><i class="bi bi-tools"></i> {$l10n->get('PLG_DEVTOOLS_BADGE')}</span>
    {/if}
    {if $devtoolsMailMode == 'catch'}
        <span class="badge text-bg-secondary me-2" title="{$l10n->get('PLG_DEVTOOLS_MAIL_MODE_CATCH')}"><i class="bi bi-envelope-slash"></i></span>
    {elseif $devtoolsMailMode == 'redirect'}
        <span class="badge text-bg-secondary me-2" title="{$l10n->get('PLG_DEVTOOLS_MAIL_MODE_REDIRECT')}"><i class="bi bi-envelope-arrow-up"></i></span>
    {/if}
    {if count($devtoolsLanguages) > 0}
        <span class="dropdown me-2">
            <a class="btn btn-sm btn-outline-secondary dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="{$l10n->get('PLG_DEVTOOLS_LANGUAGE_SWITCHER')}">
                <i class="bi bi-translate"></i> {$devtoolsCurrentLanguage|escape}
            </a>
            <ul class="dropdown-menu dropdown-menu-end admidio-devtools-languages">
                {foreach $devtoolsLanguages as $language}
                    <li><a class="dropdown-item{if $language.active} active{/if}" href="{$language.url}">{$language.name|escape} <span class="text-muted small">({$language.code|escape})</span></a></li>
                {/foreach}
            </ul>
        </span>
    {/if}
</div>
