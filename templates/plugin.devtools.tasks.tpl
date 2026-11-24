{include file='plugin.devtools.nav.tpl'}

<p>{$tasksDescription|escape}</p>

{foreach $errors as $error}
    <div class="alert alert-danger"><pre class="mb-0">{$error|escape}</pre></div>
{/foreach}

<form method="post" action="{$runUrl}" class="mb-3" onsubmit="this.querySelector('button').disabled = true;">
    <input type="hidden" name="adm_csrf_token" value="{$devtoolsCsrfToken}">
    <label class="form-label" for="devtools_command">{$l10n->get('PLG_DEVTOOLS_TASK_COMMAND')}</label>
    <div class="input-group">
        <span class="input-group-text">php admidio</span>
        <input class="form-control font-monospace" id="devtools_command" name="command" value="{$commandLine|escape}" placeholder="version" required>
        <button type="submit" class="btn btn-primary"><i class="bi bi-play-fill"></i> {$l10n->get('PLG_DEVTOOLS_TASK_RUN')}</button>
    </div>
    <div class="form-text">{$l10n->get('PLG_DEVTOOLS_TASK_COMMAND_DESC')}</div>
</form>

{if $result}
    <div class="card mb-4 {if $result.exitCode === 0}border-success{else}border-danger{/if}">
        <div class="card-header"><code>{$result.command|escape}</code> &middot; {$l10n->get('PLG_DEVTOOLS_TASK_EXIT_CODE')}: <strong>{$result.exitCode}</strong> &middot; {$result.duration} s</div>
        <div class="card-body">
            {if $result.output != ''}<pre class="admidio-devtools-pre">{$result.output|escape}</pre>{/if}
            {if $result.errors != ''}<pre class="admidio-devtools-pre text-danger">{$result.errors|escape}</pre>{/if}
        </div>
    </div>
{/if}

<h4>{$l10n->get('PLG_DEVTOOLS_MAINTENANCE_JOBS')}</h4>
<div class="table-responsive mb-4">
    <table class="table table-sm table-hover admidio-devtools-table">
        <tbody>
            {foreach $jobs as $job}
                <tr>
                    <td><code>{$job.job|escape}</code></td>
                    <td>{$job.description|default:''|escape} <small class="text-muted">{$job.origin|default:''|escape}</small></td>
                    <td>
                        <form method="post" action="{$runJobUrl}" onsubmit="this.querySelector('button').disabled = true;">
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

<h4>{$l10n->get('PLG_DEVTOOLS_COMMANDS')}</h4>
<input type="search" class="form-control mb-3" data-devtools-filter="adm_plg_devtools_command_table" placeholder="{$l10n->get('PLG_DEVTOOLS_SEARCH')}">
<div class="table-responsive">
    <table id="adm_plg_devtools_command_table" class="table table-sm table-hover admidio-devtools-table">
        <thead><tr><th>{$l10n->get('PLG_DEVTOOLS_TASK_COMMAND')}</th><th>{$l10n->get('SYS_DESCRIPTION')}</th><th>{$l10n->get('PLG_DEVTOOLS_HOOK_OWNER')}</th></tr></thead>
        <tbody>
            {foreach $commands as $command}
                <tr>
                    <td><a href="#" onclick="document.getElementById('devtools_command').value = this.dataset.usage; document.getElementById('devtools_command').focus(); return false;" data-usage="{$command.name|escape}"><code>{$command.name|escape}</code></a></td>
                    <td>{$command.description|escape}<br><small class="text-muted font-monospace">{$command.usage|escape}</small>
                        {if $command.unavailable}<br><small class="text-warning">{$command.unavailable|escape}</small>{/if}</td>
                    <td>{$command.component|escape}</td>
                </tr>
            {/foreach}
        </tbody>
    </table>
</div>
