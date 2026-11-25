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
    <div class="row g-2 mb-3 align-items-center">
        <div class="col-md-8"><input type="search" class="form-control" data-devtools-filter="adm_plg_devtools_hook_table" placeholder="{$l10n->get('PLG_DEVTOOLS_SEARCH')}"></div>
        <div class="col-md-4 text-muted small">{count($hooks)} {$l10n->get('PLG_DEVTOOLS_HOOKS_COUNT')}{if $view == 'registrations'}, {$callbackCount} {$l10n->get('PLG_DEVTOOLS_HOOK_CALLBACKS')}{/if}</div>
    </div>
    <div class="table-responsive">
        <table id="adm_plg_devtools_hook_table" class="table table-sm admidio-devtools-table admidio-devtools-grouped">
            <colgroup><col style="width: 28%"><col></colgroup>
            {foreach $hooks as $hook}
                <tbody data-devtools-group>
                    <tr>
                        <th class="admidio-devtools-group-head">
                            <code>{$hook.name|escape|replace:'_':'_<wbr>'}</code>
                            <div class="small fw-normal text-muted">{$hook.kinds|join:', '|escape}{if $hook.entity} &middot; entity{/if}</div>
                        </th>
                        <td>
                            {if $view == 'registrations'}
                                <ol class="mb-1 ps-3">
                                    {foreach $hook.callbacks as $callback}
                                        <li>
                                            <code>{$callback.callback|escape}</code>
                                            <span class="badge text-bg-light">{$callback.owner|escape}</span>
                                            <small class="text-muted">{$l10n->get('PLG_DEVTOOLS_HOOK_PRIORITY')} {$callback.priority}{if $callback.acceptedArgs !== null} &middot; {$callback.acceptedArgs} args{/if} &middot; {$callback.key|escape}</small>
                                            {if $callback.location != ''}<br><small class="text-muted">{$callback.location|escape}</small>{/if}
                                        </li>
                                    {/foreach}
                                </ol>
                                {if count($hook.sites) > 0}
                                    <details class="small"><summary>{$l10n->get('PLG_DEVTOOLS_HOOK_SITES')} ({count($hook.sites)})</summary>
                                        {foreach $hook.sites as $site}<div class="text-muted">{$site.method|escape} &middot; {$site.file|escape}:{$site.line}</div>{/foreach}
                                    </details>
                                {elseif !$hook.entity}
                                    <small class="text-warning">{$l10n->get('PLG_DEVTOOLS_HOOK_NOT_DISPATCHED')}</small>
                                {/if}
                            {else}
                                {foreach $hook.sites as $site}
                                    <div class="small"><span class="text-muted">{$site.method|escape}</span> &middot; {$site.file|escape}:{$site.line} <span class="badge text-bg-light">{$site.owner|escape}</span></div>
                                {/foreach}
                                <small class="text-muted">{count($hook.callbacks)} {$l10n->get('PLG_DEVTOOLS_HOOK_CALLBACKS')}{if count($hook.owners) > 0}: {$hook.owners|join:', '|escape}{/if}</small>
                            {/if}
                        </td>
                    </tr>
                </tbody>
            {/foreach}
        </table>
    </div>
{/if}
