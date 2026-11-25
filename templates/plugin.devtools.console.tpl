{include file='plugin.devtools.nav.tpl'}

<p>{$consoleDescription|escape}</p>

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

<h4>{$l10n->get('PLG_DEVTOOLS_COMMANDS')}</h4>
<input type="search" class="form-control mb-3" id="devtools_console_search" placeholder="{$l10n->get('PLG_DEVTOOLS_SEARCH')}">
<div class="accordion" id="adm_plg_devtools_console_areas">
    {foreach $areas as $area => $commands}
        <div class="accordion-item" data-devtools-area>
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#adm_plg_devtools_area_{$area|escape}" aria-expanded="false">
                    <code class="me-2">{$area|escape}</code> <span class="badge text-bg-secondary">{count($commands)}</span>
                </button>
            </h2>
            <div id="adm_plg_devtools_area_{$area|escape}" class="accordion-collapse collapse">
                <div class="accordion-body">
                    {foreach $commands as $command}
                        <div class="admidio-devtools-command" data-devtools-command>
                            <h6 class="mb-1">
                                <a href="#" class="text-decoration-none" data-devtools-use="{$command.name|escape}" title="{$l10n->get('PLG_DEVTOOLS_CONSOLE_USE')}"><code>{$command.name|escape}</code></a>
                                {if $command.dryRun}<span class="badge text-bg-info">--dry-run</span>{/if}
                                {if $command.component != ''}<small class="text-muted ms-2">{$command.component|escape}</small>{/if}
                            </h6>
                            <p class="mb-1">{$command.description|escape}</p>
                            {if $command.unavailable != ''}<p class="mb-1 text-warning small">{$command.unavailable|escape}</p>{/if}
                            <pre class="admidio-devtools-pre mb-1">{$command.usage|escape}</pre>
                            {if count($command.arguments) > 0 || count($command.options) > 0}
                                <table class="table table-sm mb-1 small">
                                    <tbody>
                                        {foreach $command.arguments as $argument}
                                            <tr><td class="text-nowrap"><code>{$argument.name|default:''|upper|escape}</code>{if !empty($argument.required)} *{/if}</td><td>{$argument.description|default:''|escape}</td></tr>
                                        {/foreach}
                                        {foreach $command.options as $option}
                                            <tr><td class="text-nowrap"><code>--{$option.name|default:''|escape}{if empty($option.flag) && !empty($option.value)}={$option.value|escape}{/if}</code>{if !empty($option.required)} *{/if}</td>
                                                <td>{$option.description|default:''|escape}{if !empty($option.values)} <span class="text-muted">({$option.values|join:', '|escape})</span>{/if}</td></tr>
                                        {/foreach}
                                    </tbody>
                                </table>
                            {/if}
                            {foreach $command.examples as $example}
                                <div class="small"><code>{$example|escape}</code></div>
                            {/foreach}
                        </div>
                    {/foreach}
                </div>
            </div>
        </div>
    {/foreach}
</div>
