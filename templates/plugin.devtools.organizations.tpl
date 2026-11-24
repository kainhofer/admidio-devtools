{include file='plugin.devtools.nav.tpl'}

<p>{$l10n->get('PLG_DEVTOOLS_ORGANIZATIONS_DESC')}</p>

<div class="table-responsive">
    <table class="table table-hover admidio-devtools-table">
        <thead><tr><th>{$l10n->get('SYS_ORGANIZATION')}</th><th>{$l10n->get('PLG_DEVTOOLS_SHORT_NAME')}</th><th></th></tr></thead>
        <tbody>
            {foreach $organizations as $org}
                <tr{if $org.current} class="table-primary"{/if}>
                    <td>{$org.longname|escape}</td>
                    <td><code>{$org.shortname|escape}</code></td>
                    <td>
                        {if $org.current}
                            <span class="badge text-bg-primary">{$l10n->get('PLG_DEVTOOLS_ORGANIZATION_CURRENT')}</span>
                        {elseif $org.member}
                            <form method="post" action="{$switchUrl}">
                                <input type="hidden" name="adm_csrf_token" value="{$devtoolsCsrfToken}">
                                <input type="hidden" name="organization" value="{$org.shortname|escape}">
                                <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-left-right"></i> {$l10n->get('PLG_DEVTOOLS_ORGANIZATION_SWITCH')}</button>
                            </form>
                        {else}
                            <small class="text-muted">{$l10n->get('PLG_DEVTOOLS_ORGANIZATION_NOT_MEMBER')}</small>
                        {/if}
                    </td>
                </tr>
            {/foreach}
        </tbody>
    </table>
</div>
