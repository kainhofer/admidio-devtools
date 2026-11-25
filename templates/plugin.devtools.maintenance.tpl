{include file='plugin.devtools.nav.tpl'}

<p>{$l10n->get('PLG_DEVTOOLS_MAINTENANCE_DESC')}</p>

{foreach $errors as $error}
    <div class="alert alert-danger"><pre class="mb-0">{$error|escape}</pre></div>
{/foreach}

{if $result}
    <div class="card mb-4 {if $result.exitCode === 0}border-success{else}border-danger{/if}">
        <div class="card-header"><code>{$result.command|escape}</code> &middot; {$l10n->get('PLG_DEVTOOLS_TASK_EXIT_CODE')}: <strong>{$result.exitCode}</strong> &middot; {$result.duration} s</div>
        <div class="card-body">
            {if $result.output != ''}<pre class="admidio-devtools-pre">{$result.output|escape}</pre>{/if}
            {if $result.errors != ''}<pre class="admidio-devtools-pre text-danger">{$result.errors|escape}</pre>{/if}
        </div>
    </div>
{/if}

<h4>{$l10n->get('PLG_DEVTOOLS_MAINTENANCE_SCHEDULE')}</h4>
{if count($organizations) === 0}
    <div class="alert alert-warning">{$l10n->get('PLG_DEVTOOLS_MAINTENANCE_NO_RUNS', array($logDays))}</div>
{else}
    <div class="table-responsive mb-4">
        <table class="table table-sm admidio-devtools-table">
            <thead><tr>
                <th>{$l10n->get('SYS_ORGANIZATION')}</th>
                <th>{$l10n->get('PLG_DEVTOOLS_MAINTENANCE_LAST_RUN')}</th>
                <th>{$l10n->get('PLG_DEVTOOLS_MAINTENANCE_LAST_SUCCESS')}</th>
                <th>{$l10n->get('PLG_DEVTOOLS_MAINTENANCE_INTERVAL')}</th>
                <th>{$l10n->get('PLG_DEVTOOLS_MAINTENANCE_RUNS', array($logDays))}</th>
            </tr></thead>
            <tbody>
                {foreach $organizations as $org}
                    <tr class="{if $org.overdue}table-danger{elseif $org.last.exitCode !== 0}table-warning{/if}">
                        <td>{if $org.organization == ''}<em>{$l10n->get('PLG_DEVTOOLS_MAINTENANCE_DEFAULT_ORGANIZATION')}</em>{else}{$org.organization|escape}{/if}</td>
                        <td>{$org.last.time|escape} ({$org.ageText}) &middot; exit {$org.last.exitCode}
                            {if $org.overdue}<br><strong>{$l10n->get('PLG_DEVTOOLS_MAINTENANCE_OVERDUE')}</strong>{/if}</td>
                        <td>{if $org.lastSuccess}{$org.lastSuccess.time|escape}{else}&ndash;{/if}</td>
                        <td>{if $org.intervalText != ''}{$org.intervalText}{else}&ndash;{/if}</td>
                        <td>{$org.runs}{if $org.failed > 0} <span class="badge text-bg-danger">{$org.failed} {$l10n->get('PLG_DEVTOOLS_MAINTENANCE_FAILED')}</span>{/if}</td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
    </div>
    <p class="form-text">{$l10n->get('PLG_DEVTOOLS_MAINTENANCE_LOG_DESC')}</p>
{/if}

<h4>{$l10n->get('PLG_DEVTOOLS_MAINTENANCE_JOBS')}</h4>
<form method="post" action="{$runUrl}" class="mb-2" onsubmit="this.querySelector('button').disabled = true;">
    <input type="hidden" name="adm_csrf_token" value="{$devtoolsCsrfToken}">
    <input type="hidden" name="job" value="">
    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-play-fill"></i> {$l10n->get('PLG_DEVTOOLS_MAINTENANCE_RUN_ALL', array($currentOrganization|escape))}</button>
</form>
<div class="table-responsive mb-4">
    <table class="table table-sm table-hover admidio-devtools-table">
        <thead><tr><th>{$l10n->get('PLG_DEVTOOLS_MAINTENANCE_JOB')}</th><th>{$l10n->get('SYS_DESCRIPTION')}</th><th>{$l10n->get('PLG_DEVTOOLS_MAINTENANCE_LAST_RUN')}</th><th></th></tr></thead>
        <tbody>
            {foreach $jobs as $job}
                <tr>
                    <td><code>{$job.job|escape}</code><br><small class="text-muted">{$job.origin|default:''|escape}</small></td>
                    <td>{$job.description|default:''|escape}</td>
                    <td>{if $job.lastRun}{$job.lastRun.time|escape} <small class="text-muted">{$job.lastRun.organization|escape}, exit {$job.lastRun.exitCode}</small>{else}&ndash;{/if}</td>
                    <td>
                        <form method="post" action="{$runUrl}" onsubmit="this.querySelector('button').disabled = true;">
                            <input type="hidden" name="adm_csrf_token" value="{$devtoolsCsrfToken}">
                            <input type="hidden" name="job" value="{$job.job|escape}">
                            <button type="submit" class="btn btn-sm btn-outline-primary" title="{$l10n->get('PLG_DEVTOOLS_TASK_RUN')}"><i class="bi bi-play-fill"></i></button>
                        </form>
                    </td>
                </tr>
            {/foreach}
        </tbody>
    </table>
</div>

{if count($recentRuns) > 0}
    <h4>{$l10n->get('PLG_DEVTOOLS_MAINTENANCE_RECENT')}</h4>
    <div class="table-responsive">
        <table class="table table-sm admidio-devtools-table">
            <thead><tr><th>{$l10n->get('SYS_TIME')}</th><th>{$l10n->get('SYS_ORGANIZATION')}</th><th>{$l10n->get('PLG_DEVTOOLS_MAINTENANCE_JOBS')}</th><th>Exit</th><th>{$l10n->get('PLG_DEVTOOLS_USER')}</th></tr></thead>
            <tbody>
                {foreach $recentRuns as $run}
                    <tr{if $run.exitCode !== 0} class="table-warning"{/if}>
                        <td class="text-nowrap">{$run.time|escape}</td>
                        <td>{$run.organization|escape}</td>
                        <td><small>{$run.jobs|join:', '|escape}</small></td>
                        <td>{$run.exitCode}</td>
                        <td><small>{$run.actor|escape} ({$run.account|escape})</small></td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
    </div>
{/if}
