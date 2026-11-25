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
                {if $snapshotPathSource != 'config'}
                    <a class="btn btn-sm btn-outline-secondary ms-2" data-bs-toggle="collapse" href="#adm_plg_devtools_directory" role="button" aria-expanded="false"><i class="bi bi-gear"></i> {$l10n->get('PLG_DEVTOOLS_SNAPSHOT_DIRECTORY_CHANGE')}</a>
                {/if}
                {if $snapshotPathInWebRoot}<div class="form-text text-warning">{$l10n->get('PLG_DEVTOOLS_SNAPSHOT_DIRECTORY_IN_WEBROOT')}</div>{/if}
                {if $snapshotPathSource == 'config'}
                    <div class="form-text">{$l10n->get('PLG_DEVTOOLS_SNAPSHOT_DIRECTORY_FROM_CONFIG')}</div>
                {else}
                    <form id="adm_plg_devtools_directory" method="post" action="{$directoryUrl}" class="collapse mt-2">
                        <input type="hidden" name="adm_csrf_token" value="{$devtoolsCsrfToken}">
                        <div class="input-group">
                            <input class="form-control font-monospace" name="path" value="{$snapshotPathSetting|escape}" placeholder="/var/lib/admidio-snapshots">
                            <button type="submit" class="btn btn-primary">{$l10n->get('SYS_SAVE')}</button>
                        </div>
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" id="devtools_move" name="move" value="1" checked>
                            <label class="form-check-label" for="devtools_move">{$l10n->get('PLG_DEVTOOLS_SNAPSHOT_DIRECTORY_MOVE')}</label>
                        </div>
                        <div class="form-text">{$l10n->get('PLG_DEVTOOLS_SNAPSHOT_DIRECTORY_DESC')}</div>
                    </form>
                {/if}
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
                        <td><strong>{if $snapshot.label != ''}{$snapshot.label|escape}{else}{$snapshot.name|escape}{/if}</strong>
                            <br><small class="text-muted">{$snapshot.name|escape} &middot; PHP {$snapshot.php|escape} &middot; {$snapshot.dbType|escape} {$snapshot.dbVersion|escape}</small></td>
                        <td>{$snapshot.version|escape}</td>
                        <td>{$snapshot.comment|escape}
                            <form method="post" action="{$updateUrl}" class="collapse mt-2" id="adm_plg_devtools_edit_{$snapshot@iteration}">
                                <input type="hidden" name="adm_csrf_token" value="{$devtoolsCsrfToken}">
                                <input type="hidden" name="snapshot" value="{$snapshot.name|escape}">
                                <input class="form-control form-control-sm mb-1" name="label" maxlength="60" value="{$snapshot.label|escape}" placeholder="{$l10n->get('PLG_DEVTOOLS_SNAPSHOT_LABEL')}">
                                <textarea class="form-control form-control-sm mb-1" name="comment" rows="2" maxlength="1000" placeholder="{$l10n->get('PLG_DEVTOOLS_SNAPSHOT_COMMENT')}">{$snapshot.comment|escape}</textarea>
                                <button type="submit" class="btn btn-sm btn-primary">{$l10n->get('SYS_SAVE')}</button>
                            </form>
                        </td>
                        <td>{if $snapshot.includeData}{$l10n->get('PLG_DEVTOOLS_SNAPSHOT_SCOPE_DATA')}{else}{$l10n->get('PLG_DEVTOOLS_SNAPSHOT_SCOPE_CODE')}{/if}</td>
                        <td>{$snapshot.fileCount} ({$snapshot.fileSizeText})</td>
                        <td>{$snapshot.tableCount} / {$snapshot.rowCount} ({$snapshot.databaseSizeText})</td>
                        <td class="text-nowrap">
                            <a class="btn btn-sm btn-outline-warning" href="{$snapshot.restoreUrl|escape}" target="_blank" rel="noopener" title="{$l10n->get('PLG_DEVTOOLS_RESTORE_BUTTON')}"><i class="bi bi-arrow-counterclockwise"></i></a>
                            <a class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" href="#adm_plg_devtools_edit_{$snapshot@iteration}" role="button" title="{$l10n->get('PLG_DEVTOOLS_SNAPSHOT_EDIT')}"><i class="bi bi-pencil-square"></i></a>
                            <form method="post" action="{$deleteUrl}" class="d-inline" onsubmit="return confirm('{$l10n->get('PLG_DEVTOOLS_SNAPSHOT_DELETE_CONFIRM')|escape:'javascript'}');">
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
