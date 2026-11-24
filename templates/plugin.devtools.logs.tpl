{include file='plugin.devtools.nav.tpl'}

<p>{$l10n->get('PLG_DEVTOOLS_LOGS_DESC')}</p>

<ul class="nav nav-tabs mb-3">
    {foreach $views as $item}
        <li class="nav-item"><a class="nav-link{if $item.active} active{/if}" href="{$item.url}">{$item.label|escape}</a></li>
    {/foreach}
</ul>

{if $view == 'files'}
    {if count($files) === 0}
        <div class="alert alert-info">{$l10n->get('PLG_DEVTOOLS_LOG_NO_FILES')}</div>
    {else}
        <form method="get" action="{$formUrl}" class="row g-2 align-items-end mb-3">
            <input type="hidden" name="view" value="files">
            <div class="col-md-4">
                <label class="form-label" for="devtools_file">{$l10n->get('PLG_DEVTOOLS_LOG_FILE')}</label>
                <select class="form-select" id="devtools_file" name="file">
                    {foreach $files as $logFile}
                        <option value="{$logFile.id|escape}"{if $logFile.id == $file} selected{/if}>{$logFile.name|escape} ({$logFile.size}, {$logFile.time})</option>
                    {/foreach}
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="devtools_level">{$l10n->get('PLG_DEVTOOLS_LOG_LEVEL')}</label>
                <select class="form-select" id="devtools_level" name="level">
                    <option value="">{$l10n->get('SYS_ALL')}</option>
                    {foreach $levels as $logLevel}
                        <option value="{$logLevel}"{if $logLevel == $level} selected{/if}>&ge; {$logLevel}</option>
                    {/foreach}
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="devtools_search">{$l10n->get('PLG_DEVTOOLS_SEARCH')}</label>
                <input class="form-control" id="devtools_search" name="search" value="{$search|escape}">
            </div>
            <div class="col-md-1">
                <label class="form-label" for="devtools_limit">{$l10n->get('PLG_DEVTOOLS_LOG_LIMIT')}</label>
                <input class="form-control" type="number" id="devtools_limit" name="limit" value="{$limit}" min="1" max="5000">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i></button>
                <a class="btn btn-outline-secondary" href="{$downloadUrl}" title="{$l10n->get('PLG_DEVTOOLS_DOWNLOAD')}"><i class="bi bi-download"></i></a>
            </div>
        </form>

        {if count($entries) === 0}
            <div class="alert alert-info">{$l10n->get('PLG_DEVTOOLS_LOG_NO_ENTRIES')}</div>
        {else}
            <div class="table-responsive">
                <table class="table table-sm admidio-devtools-table">
                    <tbody>
                        {foreach $entries as $entry}
                            <tr class="{if $entry.level == 'ERROR' || $entry.level == 'CRITICAL' || $entry.level == 'ALERT' || $entry.level == 'EMERGENCY'}table-danger{elseif $entry.level == 'WARNING'}table-warning{/if}">
                                <td class="text-nowrap"><small>{$entry.time|escape}</small></td>
                                <td><small>{$entry.level|escape}</small></td>
                                <td><pre class="mb-0 admidio-devtools-pre p-1">{$entry.text|escape}</pre></td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        {/if}
    {/if}
{else}
    {if !$catcherEnabled}
        <div class="alert alert-info">{$l10n->get('PLG_DEVTOOLS_EXCEPTION_CATCHER_DISABLED')}</div>
    {/if}
    {if $exception}
        <p><a href="{$exceptionsUrl}"><i class="bi bi-arrow-left"></i> {$l10n->get('PLG_DEVTOOLS_EXCEPTIONS')}</a></p>
        <dl class="row">
            <dt class="col-sm-2">{$l10n->get('SYS_TIME')}</dt><dd class="col-sm-10">{$exception.time|escape}</dd>
            <dt class="col-sm-2">URL</dt><dd class="col-sm-10"><code>{$exception.method|escape} {$exception.url|escape}</code></dd>
            <dt class="col-sm-2">{$l10n->get('PLG_DEVTOOLS_USER')}</dt><dd class="col-sm-10">{$exception.user}</dd>
            <dt class="col-sm-2">GET</dt><dd class="col-sm-10">{$exception.get|join:', '|escape}</dd>
            <dt class="col-sm-2">POST</dt><dd class="col-sm-10">{$exception.post|join:', '|escape}</dd>
        </dl>
        {foreach $exception.exceptions as $item}
            <div class="card mb-3">
                <div class="card-header"><strong>{$item.class|escape}</strong>: {$item.message|escape}</div>
                <div class="card-body">
                    <p><code>{$item.file|escape}:{$item.line}</code></p>
                    <pre class="admidio-devtools-pre">{$item.trace|escape}</pre>
                </div>
            </div>
        {/foreach}
    {elseif count($exceptions) === 0}
        <div class="alert alert-info">{$l10n->get('PLG_DEVTOOLS_EXCEPTIONS_NONE')}</div>
    {else}
        <form method="post" action="{$deleteExceptionsUrl}" class="mb-3">
            <input type="hidden" name="adm_csrf_token" value="{$devtoolsCsrfToken}">
            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> {$l10n->get('PLG_DEVTOOLS_DELETE_ALL')}</button>
        </form>
        <div class="table-responsive">
            <table class="table table-sm table-hover admidio-devtools-table">
                <thead><tr><th>{$l10n->get('SYS_TIME')}</th><th>{$l10n->get('PLG_DEVTOOLS_EXCEPTION')}</th><th>URL</th></tr></thead>
                <tbody>
                    {foreach $exceptions as $entry}
                        <tr>
                            <td class="text-nowrap"><a href="{$entry.link}">{$entry.time|escape}</a></td>
                            <td><strong>{$entry.first.class|escape}</strong>: {$entry.first.message|escape}<br><small class="text-muted">{$entry.first.file|escape}:{$entry.first.line}</small></td>
                            <td><small>{$entry.method|escape} {$entry.url|escape}</small></td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>
    {/if}
{/if}
