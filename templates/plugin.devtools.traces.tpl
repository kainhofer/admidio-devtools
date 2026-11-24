{include file='plugin.devtools.nav.tpl'}

<p>{$l10n->get('PLG_DEVTOOLS_HOOK_TRACES_DESC')}</p>
{if !$enabled}
    <div class="alert alert-info">{$l10n->get('PLG_DEVTOOLS_HOOK_TRACE_DISABLED')}</div>
{/if}

{if $trace}
    <p><a href="{$listUrl}"><i class="bi bi-arrow-left"></i> {$l10n->get('PLG_DEVTOOLS_HOOK_TRACES')}</a></p>
    <dl class="row">
        <dt class="col-sm-2">URL</dt><dd class="col-sm-10"><code>{$trace.method|escape} {$trace.url|escape}</code></dd>
        <dt class="col-sm-2">{$l10n->get('SYS_TIME')}</dt><dd class="col-sm-10">{$trace.time|escape} &middot; {$trace.duration} ms &middot; {count($trace.calls)} {$l10n->get('PLG_DEVTOOLS_HOOK_CALLS')}{if $trace.dropped > 0} (+{$trace.dropped}){/if}</dd>
    </dl>
    <h5>{$l10n->get('PLG_DEVTOOLS_HOOK_SLOWEST')}</h5>
    <ul>
        {foreach $trace.slowest as $hook => $duration}
            <li><code>{$hook|escape}</code>: {$duration|string_format:"%.2f"} ms</li>
        {/foreach}
    </ul>
    <input type="search" class="form-control mb-3" data-devtools-filter="adm_plg_devtools_trace_table" placeholder="{$l10n->get('PLG_DEVTOOLS_SEARCH')}">
    <div class="table-responsive">
        <table id="adm_plg_devtools_trace_table" class="table table-sm table-hover admidio-devtools-table">
            <thead>
                <tr>
                    <th>ms</th>
                    <th>{$l10n->get('PLG_DEVTOOLS_HOOK')}</th>
                    <th>{$l10n->get('PLG_DEVTOOLS_HOOK_CALLBACK')}</th>
                    <th>{$l10n->get('PLG_DEVTOOLS_HOOK_ARGUMENTS')}</th>
                    <th>{$l10n->get('PLG_DEVTOOLS_HOOK_DURATION')}</th>
                </tr>
            </thead>
            <tbody>
                {foreach $trace.calls as $call}
                    <tr{if isset($call.exception)} class="table-danger"{/if}>
                        <td>{$call.time}</td>
                        <td style="padding-left: {$call.depth * 1.25 + 0.25}rem"><code>{$call.hook|escape}</code> <small class="text-muted">{$call.type|escape}</small></td>
                        <td><small>{$call.callback|escape}</small></td>
                        <td><small>{$call.args|join:', '|escape}
                            {if isset($call.changed) && $call.changed}<br><strong>{$l10n->get('PLG_DEVTOOLS_HOOK_CHANGED')}:</strong> {$call.before|escape} &rarr; {$call.after|escape}{/if}
                            {if isset($call.answer) && $call.answer !== null}<br><strong>{$l10n->get('PLG_DEVTOOLS_HOOK_ANSWER')}:</strong> {$call.answer|escape}{/if}
                            {if isset($call.exception)}<br><strong>{$call.exception|escape}</strong>{/if}</small></td>
                        <td>{$call.duration}</td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
    </div>
{else}
    {if count($traces) === 0}
        <div class="alert alert-info">{$l10n->get('PLG_DEVTOOLS_HOOK_NO_TRACES')}</div>
    {else}
        <form method="post" action="{$deleteUrl}" class="mb-3">
            <input type="hidden" name="adm_csrf_token" value="{$devtoolsCsrfToken}">
            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> {$l10n->get('PLG_DEVTOOLS_DELETE_ALL')}</button>
        </form>
        <div class="table-responsive">
            <table class="table table-sm table-hover admidio-devtools-table">
                <thead><tr><th>{$l10n->get('SYS_TIME')}</th><th>URL</th><th>{$l10n->get('PLG_DEVTOOLS_HOOK_CALLS')}</th><th>ms</th></tr></thead>
                <tbody>
                    {foreach $traces as $entry}
                        <tr>
                            <td><a href="{$entry.link}">{$entry.time|escape}</a></td>
                            <td><code>{$entry.method|escape} {$entry.url|escape}</code></td>
                            <td>{$entry.count}</td>
                            <td>{$entry.duration}</td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>
    {/if}
{/if}
