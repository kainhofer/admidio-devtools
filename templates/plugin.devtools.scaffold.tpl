{include file='plugin.devtools.nav.tpl'}

<p>{$l10n->get('PLG_DEVTOOLS_SCAFFOLD_DESC')}</p>

<form method="post" action="{$createUrl}" class="card card-body">
    <input type="hidden" name="adm_csrf_token" value="{$devtoolsCsrfToken}">
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="devtools_id">{$l10n->get('PLG_DEVTOOLS_SCAFFOLD_ID')}</label>
            <input class="form-control font-monospace" id="devtools_id" name="id" pattern="[a-z][a-z0-9]*(-[a-z0-9]+)*" maxlength="40" required placeholder="my-plugin">
            <div class="form-text">{$l10n->get('PLG_DEVTOOLS_SCAFFOLD_ID_DESC')}</div>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="devtools_name">{$l10n->get('SYS_NAME')}</label>
            <input class="form-control" id="devtools_name" name="name" maxlength="100">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="devtools_author">{$l10n->get('PLG_DEVTOOLS_SCAFFOLD_AUTHOR')}</label>
            <input class="form-control" id="devtools_author" name="author" maxlength="100" value="{$author|escape}">
        </div>
        <div class="col-12">
            <label class="form-label" for="devtools_description">{$l10n->get('SYS_DESCRIPTION')}</label>
            <input class="form-control" id="devtools_description" name="description" maxlength="255">
        </div>
    </div>
    <div class="mt-3">
        {foreach ['page' => 'PLG_DEVTOOLS_SCAFFOLD_PAGE', 'setting' => 'PLG_DEVTOOLS_SCAFFOLD_SETTING', 'cli' => 'PLG_DEVTOOLS_SCAFFOLD_CLI', 'tests' => 'PLG_DEVTOOLS_SCAFFOLD_TESTS', 'release_workflow' => 'PLG_DEVTOOLS_SCAFFOLD_RELEASE_WORKFLOW'] as $option => $label}
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="devtools_{$option}" name="{$option}" value="1" checked>
                <label class="form-check-label" for="devtools_{$option}">{$l10n->get($label)}</label>
            </div>
        {/foreach}
    </div>
    <div class="mt-3">
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" id="devtools_target_directory" name="target" value="directory" checked>
            <label class="form-check-label" for="devtools_target_directory">{$l10n->get('PLG_DEVTOOLS_SCAFFOLD_TARGET_DIRECTORY')}</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" id="devtools_target_zip" name="target" value="zip">
            <label class="form-check-label" for="devtools_target_zip">{$l10n->get('PLG_DEVTOOLS_SCAFFOLD_TARGET_ZIP')}</label>
        </div>
    </div>
    <div class="mt-3">
        <button type="submit" class="btn btn-primary"><i class="bi bi-box-seam"></i> {$l10n->get('PLG_DEVTOOLS_SCAFFOLD_CREATE')}</button>
        <a class="btn btn-outline-secondary" href="{$pluginManagerUrl}">{$l10n->get('PLG_DEVTOOLS_SCAFFOLD_PLUGIN_MANAGER')}</a>
    </div>
</form>
