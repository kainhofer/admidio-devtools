{include file='plugin.devtools.nav.tpl'}

<p>{$l10n->get('PLG_DEVTOOLS_PREFERENCES_DIFF_DESC')}</p>

<form method="get" action="{$formUrl}" class="row g-2 align-items-end mb-3">
    <div class="col-md-5">
        <label class="form-label" for="devtools_org">{$l10n->get('SYS_ORGANIZATION')}</label>
        <select class="form-select" id="devtools_org" name="org" onchange="this.form.submit()">
            {foreach $organizations as $org}
                <option value="{$org.id}"{if $org.id == $organization} selected{/if}>{$org.longname|escape} ({$org.shortname|escape})</option>
            {/foreach}
        </select>
    </div>
    <div class="col-md-4">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="devtools_all" name="all" value="1"{if $showAll} checked{/if} onchange="this.form.submit()">
            <label class="form-check-label" for="devtools_all">{$l10n->get('PLG_DEVTOOLS_PREFERENCES_SHOW_ALL')}</label>
        </div>
    </div>
</form>

<p>
    <span class="badge text-bg-primary">{$l10n->get('PLG_DEVTOOLS_PREFERENCE_CHANGED')}: {$counts.changed|default:0}</span>
    <span class="badge text-bg-warning">{$l10n->get('PLG_DEVTOOLS_PREFERENCE_MISSING')}: {$counts.missing|default:0}</span>
    <span class="badge text-bg-danger">{$l10n->get('PLG_DEVTOOLS_PREFERENCE_UNKNOWN')}: {$counts.unknown|default:0}</span>
    <span class="badge text-bg-secondary">{$l10n->get('PLG_DEVTOOLS_PREFERENCE_DEFAULT')}: {$counts.default|default:0}</span>
</p>

<input type="search" class="form-control mb-3" data-devtools-filter="adm_plg_devtools_preferences_table" placeholder="{$l10n->get('PLG_DEVTOOLS_SEARCH')}">
<div class="table-responsive">
    <table id="adm_plg_devtools_preferences_table" class="table table-sm table-hover admidio-devtools-table">
        <thead>
            <tr>
                <th>{$l10n->get('SYS_NAME')}</th>
                <th>{$l10n->get('PLG_DEVTOOLS_HOOK_OWNER')}</th>
                <th>{$l10n->get('PLG_DEVTOOLS_PREFERENCE_STATUS')}</th>
                <th>{$l10n->get('PLG_DEVTOOLS_PREFERENCE_DEFAULT_VALUE')}</th>
                <th>{$l10n->get('PLG_DEVTOOLS_PREFERENCE_VALUE')}</th>
            </tr>
        </thead>
        <tbody>
            {foreach $rows as $row}
                <tr>
                    <td><code>{$row.name|escape}</code></td>
                    <td>{$row.owner|escape}</td>
                    <td>
                        {if $row.status == 'changed'}<span class="badge text-bg-primary">{$l10n->get('PLG_DEVTOOLS_PREFERENCE_CHANGED')}</span>
                        {elseif $row.status == 'missing'}<span class="badge text-bg-warning">{$l10n->get('PLG_DEVTOOLS_PREFERENCE_MISSING')}</span>
                        {elseif $row.status == 'unknown'}<span class="badge text-bg-danger">{$l10n->get('PLG_DEVTOOLS_PREFERENCE_UNKNOWN')}</span>
                        {else}<span class="badge text-bg-secondary">{$l10n->get('PLG_DEVTOOLS_PREFERENCE_DEFAULT')}</span>{/if}
                    </td>
                    <td><small class="font-monospace">{if $row.default === null}&ndash;{else}{$row.default|escape}{/if}</small></td>
                    <td><small class="font-monospace">{if $row.value === null}&ndash;{else}{$row.value|escape}{/if}</small></td>
                </tr>
            {/foreach}
        </tbody>
    </table>
</div>
