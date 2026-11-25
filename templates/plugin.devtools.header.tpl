<div id="adm_plg_devtools_header" class="d-none d-flex align-items-center me-2">
    {foreach $devtoolsBadges as $badge}
        {if $badge.url != ''}
            <a class="btn btn-sm {$badge.class} me-2" href="{$badge.url}" title="{$badge.tooltip|escape}"><i class="bi {$badge.icon}"></i> {$badge.label|escape}</a>
        {else}
            <span class="badge {$badge.class} me-2" title="{$badge.tooltip|escape}"><i class="bi {$badge.icon}"></i> {$badge.label|escape}</span>
        {/if}
    {/foreach}
</div>
