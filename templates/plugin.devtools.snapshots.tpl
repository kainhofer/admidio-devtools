{include file='plugin.devtools.nav.tpl'}

<p>{$l10n->get('PLG_DEVTOOLS_SNAPSHOTS_DESC')}</p>

<div class="card mb-4">
    <div class="card-header">{$l10n->get('PLG_DEVTOOLS_SNAPSHOT_CREATE')}</div>
    <div class="card-body">
        <form method="post" action="{$createUrl}" onsubmit="this.querySelector('button').disabled = true;">
            <input type="hidden" name="adm_csrf_token" value="{$devtoolsCsrfToken}">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="devtools_label">{$l10n->get('PLG_DEVTOOLS_SNAPSHOT_LABEL')}</label>
                    <input class="form-control" id="devtools_label" name="label" maxlength="60" placeholder="baseline">
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="devtools_comment">{$l10n->get('PLG_DEVTOOLS_SNAPSHOT_COMMENT')}</label>
                    <input class="form-control" id="devtools_comment" name="comment" maxlength="255">
                </div>
            </div>
            <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" id="devtools_include_data" name="include_data" value="1">
                <label class="form-check-label" for="devtools_include_data">{$l10n->get('PLG_DEVTOOLS_SNAPSHOT_INCLUDE_DATA')}</label>
                <div class="form-text">{$l10n->get('PLG_DEVTOOLS_SNAPSHOT_INCLUDE_DATA_DESC')}</div>
            </div>
            <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-camera"></i> {$l10n->get('PLG_DEVTOOLS_SNAPSHOT_CREATE')}</button>
        </form>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">{$l10n->get('PLG_DEVTOOLS_RESTORE')}</div>
    <div class="card-body">
        <p>{$l10n->get('PLG_DEVTOOLS_RESTORE_DESC')}</p>
        <dl class="row">
            <dt class="col-sm-3">{$l10n->get('PLG_DEVTOOLS_RESTORE_PAGE')}</dt>
            <dd class="col-sm-9"><a href="{$restoreUrl}" target="_blank" rel="noopener">{$restoreUrl|escape}</a></dd>
            <dt class="col-sm-3">{$l10n->get('PLG_DEVTOOLS_RESTORE_KEY')}</dt>
            <dd class="col-sm-9">
                {if $restoreKey}
                    <code class="user-select-all">{$restoreKey|escape}</code>
                    <form method="post" action="{$renewKeyUrl}" class="d-inline ms-2">
                        <input type="hidden" name="adm_csrf_token" value="{$devtoolsCsrfToken}">
                        <button type="submit" class="btn btn-sm btn-outline-secondary">{$l10n->get('PLG_DEVTOOLS_RESTORE_KEY_RENEW')}</button>
                    </form>
                {else}
                    {$l10n->get('PLG_DEVTOOLS_RESTORE_KEY_NONE')}
                {/if}
            </dd>
            <dt class="col-sm-3">{$l10n->get('PLG_DEVTOOLS_COMMAND_LINE')}</dt>
            <dd class="col-sm-9"><code>{$restoreCommand|escape}</code></dd>
            <dt class="col-sm-3">{$l10n->get('PLG_DEVTOOLS_SNAPSHOT_DIRECTORY')}</dt>
            <dd class="col-sm-9"><code>{$snapshotPath|escape}</code>
                <div class="form-text">{$l10n->get('PLG_DEVTOOLS_SNAPSHOT_DIRECTORY_DESC')}</div>
                {if $snapshotPathInWebRoot}<div class="form-text text-warning">{$l10n->get('PLG_DEVTOOLS_SNAPSHOT_DIRECTORY_IN_WEBROOT')}</div>{/if}
            </dd>
        </dl>
    </div>
</div>

{if count($snapshots) === 0}
    <div class="alert alert-info">{$l10n->get('PLG_DEVTOOLS_RESTORE_NO_SNAPSHOTS')}</div>
{else}
    <div class="table-responsive">
        <table class="table table-hover admidio-devtools-table">
            <thead>
                <tr>
                    <th>{$l10n->get('PLG_DEVTOOLS_SNAPSHOT')}</th>
                    <th>{$l10n->get('PLG_DEVTOOLS_VERSION')}</th>
                    <th>{$l10n->get('PLG_DEVTOOLS_SNAPSHOT_COMMENT')}</th>
                    <th>{$l10n->get('PLG_DEVTOOLS_SNAPSHOT_SCOPE')}</th>
                    <th>{$l10n->get('PLG_DEVTOOLS_FILES')}</th>
                    <th>{$l10n->get('PLG_DEVTOOLS_DATABASE')}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                {foreach $snapshots as $snapshot}
                    <tr>
                        <td><strong>{$snapshot.name|escape}</strong><br><small class="text-muted">{$snapshot.created|escape} &middot; PHP {$snapshot.php|escape} &middot; {$snapshot.dbType|escape} {$snapshot.dbVersion|escape}</small></td>
                        <td>{$snapshot.version|escape}</td>
                        <td>{$snapshot.comment|escape}</td>
                        <td>{if $snapshot.includeData}{$l10n->get('PLG_DEVTOOLS_SNAPSHOT_SCOPE_DATA')}{else}{$l10n->get('PLG_DEVTOOLS_SNAPSHOT_SCOPE_CODE')}{/if}</td>
                        <td>{$snapshot.fileCount} ({$snapshot.fileSizeText})</td>
                        <td>{$snapshot.tableCount} / {$snapshot.rowCount} ({$snapshot.databaseSizeText})</td>
                        <td>
                            <form method="post" action="{$deleteUrl}" onsubmit="return confirm('{$l10n->get('PLG_DEVTOOLS_SNAPSHOT_DELETE_CONFIRM')|escape:'javascript'}');">
                                <input type="hidden" name="adm_csrf_token" value="{$devtoolsCsrfToken}">
                                <input type="hidden" name="snapshot" value="{$snapshot.name|escape}">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="{$l10n->get('SYS_DELETE')}"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
    </div>
{/if}
