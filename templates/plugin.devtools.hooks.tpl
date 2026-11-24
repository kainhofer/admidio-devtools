{include file='plugin.devtools.nav.tpl'}

<p>{$l10n->get('PLG_DEVTOOLS_HOOKS_DESC')}</p>

{if !$available}
    <div class="alert alert-warning">{$l10n->get('PLG_DEVTOOLS_HOOK_REGISTRY_UNAVAILABLE')}</div>
{/if}

<ul class="nav nav-tabs mb-3">
    {foreach $views as $item}
        <li class="nav-item"><a class="nav-link{if $item.active} active{/if}" href="{$item.url}">{$item.label|escape}</a></li>
    {/foreach}
</ul>

{if $view == 'warnings'}
    {if count($warnings) === 0}
        <div class="alert alert-success">{$l10n->get('PLG_DEVTOOLS_HOOK_NO_WARNINGS')}</div>
    {else}
        <ul class="list-group">
            {foreach $warnings as $warning}
                <li class="list-group-item list-group-item-{$warning.level}">{$warning.text|escape}</li>
            {/foreach}
        </ul>
    {/if}
{else}
    <input type="search" class="form-control mb-3" data-devtools-filter="adm_plg_devtools_hook_table" placeholder="{$l10n->get('PLG_DEVTOOLS_SEARCH')}">
    <div class="table-responsive">
        <table id="adm_plg_devtools_hook_table" class="table table-sm table-hover admidio-devtools-table">
            {if $view == 'registrations'}
                <thead>
                    <tr>
                        <th>{$l10n->get('PLG_DEVTOOLS_HOOK')}</th>
                        <th>{$l10n->get('PLG_DEVTOOLS_HOOK_KIND')}</th>
                        <th>{$l10n->get('PLG_DEVTOOLS_HOOK_PRIORITY')}</th>
                        <th>{$l10n->get('PLG_DEVTOOLS_HOOK_CALLBACK')}</th>
                        <th>{$l10n->get('PLG_DEVTOOLS_HOOK_OWNER')}</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach $registrations as $registration}
                        <tr>
                            <td><code>{$registration.hook|escape}</code></td>
                            <td>{$registration.type|escape}</td>
                            <td>{$registration.priority} <small class="text-muted">#{$registration.order}</small></td>
                            <td><code>{$registration.callback|escape}</code><br><small class="text-muted">{$registration.file|escape} &middot; {$registration.key|escape}{if $registration.acceptedArgs !== null} &middot; {$registration.acceptedArgs} args{/if}</small></td>
                            <td>{$registration.owner|escape}</td>
                        </tr>
                    {/foreach}
                </tbody>
            {else}
                <thead>
                    <tr>
                        <th>{$l10n->get('PLG_DEVTOOLS_HOOK')}</th>
                        <th>{$l10n->get('PLG_DEVTOOLS_HOOK_KIND')}</th>
                        <th>{$l10n->get('PLG_DEVTOOLS_HOOK_SITE')}</th>
                        <th>{$l10n->get('PLG_DEVTOOLS_HOOK_OWNER')}</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach $sites as $site}
                        <tr>
                            <td>{if $site.dynamic}<em>{$site.hook|escape}</em>{else}<code>{$site.hook|escape}</code>{/if}</td>
                            <td>{$site.kind|escape} <small class="text-muted">{$site.method|escape}</small></td>
                            <td><small>{$site.file|escape}:{$site.line}</small></td>
                            <td>{$site.owner|escape}</td>
                        </tr>
                    {/foreach}
                </tbody>
            {/if}
        </table>
    </div>
{/if}
