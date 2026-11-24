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
</form>

<div id="adm_plg_devtools_preferences_filter" class="row g-2 align-items-end mb-3">
    <div class="col-12">
        <span class="me-2">{$l10n->get('PLG_DEVTOOLS_PREFERENCE_STATUS')}:</span>
        {foreach $statuses as $status => $definition}
            <button type="button" class="btn btn-sm mb-1 {if $definition.selected}btn-{$definition.class}{else}btn-outline-{$definition.class}{/if}"
                data-devtools-status="{$status}" data-devtools-class="{$definition.class}" aria-pressed="{if $definition.selected}true{else}false{/if}">
                {$definition.label|escape}: {$counts.$status|default:0}
            </button>
        {/foreach}
        <div class="form-text">{$l10n->get('PLG_DEVTOOLS_PREFERENCES_STATUS_DESC')}</div>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="devtools_owner">{$l10n->get('PLG_DEVTOOLS_HOOK_OWNER')}</label>
        <select class="form-select" id="devtools_owner">
            <option value="">{$l10n->get('SYS_ALL')}</option>
            {foreach $owners as $owner}
                <option value="{$owner|escape}">{$owner|escape}</option>
            {/foreach}
        </select>
    </div>
    <div class="col-md-8">
        <label class="form-label" for="devtools_preferences_search">{$l10n->get('PLG_DEVTOOLS_SEARCH')}</label>
        <input type="search" class="form-control" id="devtools_preferences_search">
    </div>
</div>

<p class="text-muted small"><span id="adm_plg_devtools_preferences_count"></span> &middot; {$l10n->get('PLG_DEVTOOLS_SORT_DESC')}</p>

<div class="table-responsive">
    <table id="adm_plg_devtools_preferences_table" class="table table-sm table-hover admidio-devtools-table admidio-devtools-preferences">
        <colgroup>
            <col style="width: 34%"><col style="width: 14%"><col style="width: 10%"><col style="width: 21%"><col style="width: 21%">
        </colgroup>
        <thead>
            <tr>
                <th data-devtools-sort="name" role="button">{$l10n->get('SYS_NAME')} <i class="bi"></i></th>
                <th data-devtools-sort="owner" role="button">{$l10n->get('PLG_DEVTOOLS_HOOK_OWNER')} <i class="bi"></i></th>
                <th data-devtools-sort="status" role="button">{$l10n->get('PLG_DEVTOOLS_PREFERENCE_STATUS')} <i class="bi"></i></th>
                <th data-devtools-sort="default" role="button">{$l10n->get('PLG_DEVTOOLS_PREFERENCE_DEFAULT_VALUE')} <i class="bi"></i></th>
                <th data-devtools-sort="value" role="button">{$l10n->get('PLG_DEVTOOLS_PREFERENCE_VALUE')} <i class="bi"></i></th>
            </tr>
        </thead>
        <tbody>
            {foreach $rows as $row}
                <tr data-status="{$row.status}" data-owner="{$row.owner|escape}" data-name="{$row.name|escape}"
                    data-default="{$row.default|default:''|escape}" data-value="{$row.value|default:''|escape}">
                    <td class="admidio-devtools-name"><code>{$row.name|escape|replace:'_':'_<wbr>'}</code></td>
                    <td>{$row.owner|escape}{if $row.notLoaded}<br><small class="text-muted">{$l10n->get('PLG_DEVTOOLS_PREFERENCE_NOT_LOADED')}</small>{/if}</td>
                    <td><span class="badge text-bg-{$statuses[$row.status].class}">{$statuses[$row.status].label|escape}</span></td>
                    <td class="admidio-devtools-value"><small class="font-monospace">{if $row.default === null}&ndash;{else}{$row.default|escape}{/if}</small></td>
                    <td class="admidio-devtools-value"><small class="font-monospace">{if $row.value === null}&ndash;{else}{$row.value|escape}{/if}</small></td>
                </tr>
            {/foreach}
        </tbody>
    </table>
</div>
