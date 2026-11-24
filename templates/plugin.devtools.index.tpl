{include file='plugin.devtools.nav.tpl'}

<p>{$l10n->get('PLG_DEVTOOLS_OVERVIEW_DESC')}</p>

<div class="card mb-4">
    <div class="card-header">{$l10n->get('PLG_DEVTOOLS_STATUS')}</div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-4">{$l10n->get('PLG_DEVTOOLS_SNAPSHOT_DIRECTORY')}</dt>
            <dd class="col-sm-8"><code>{$status.snapshotPath|escape}</code></dd>
            <dt class="col-sm-4">{$l10n->get('PLG_DEVTOOLS_SNAPSHOTS')}</dt>
            <dd class="col-sm-8">{$status.snapshotCount}{if $status.lastSnapshot} ({$l10n->get('PLG_DEVTOOLS_LATEST')}: {$status.lastSnapshot|escape}){/if}</dd>
            <dt class="col-sm-4">{$l10n->get('PLG_DEVTOOLS_MAIL_MODE')}</dt>
            <dd class="col-sm-8">{$status.mailMode|escape} &middot; {$l10n->get('PLG_DEVTOOLS_MAILS')}: {$status.mailCount}</dd>
            <dt class="col-sm-4">{$l10n->get('PLG_DEVTOOLS_EXCEPTIONS')}</dt>
            <dd class="col-sm-8">{$status.exceptionCount}</dd>
            <dt class="col-sm-4">{$l10n->get('PLG_DEVTOOLS_HOOK_TRACE')}</dt>
            <dd class="col-sm-8">{if $status.hookTrace}{$l10n->get('SYS_YES')}{else}{$l10n->get('SYS_NO')}{/if} &middot; {$status.traceCount}</dd>
        </dl>
        <a class="btn btn-sm btn-outline-primary mt-3" href="{$preferencesUrl}"><i class="bi bi-gear"></i> {$l10n->get('SYS_SETTINGS')}</a>
    </div>
</div>

<div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
    {foreach $features as $feature}
        <div class="col">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title"><a href="{$feature.url}"><i class="bi {$feature.icon}"></i> {$feature.label|escape}</a></h5>
                    <p class="card-text">{$feature.description|escape}</p>
                </div>
            </div>
        </div>
    {/foreach}
</div>
