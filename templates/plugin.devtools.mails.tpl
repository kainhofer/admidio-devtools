{include file='plugin.devtools.nav.tpl'}

<p>{$l10n->get('PLG_DEVTOOLS_MAILS_DESC')}</p>
<div class="alert alert-{if $mailMode == 'off'}warning{else}info{/if}">{$l10n->get('PLG_DEVTOOLS_MAIL_MODE')}: <strong>{$mailModeText|escape}</strong></div>

{if $mail}
    <p><a href="{$listUrl}"><i class="bi bi-arrow-left"></i> {$l10n->get('PLG_DEVTOOLS_MAILS')}</a></p>
    <dl class="row">
        <dt class="col-sm-2">{$l10n->get('SYS_SUBJECT')}</dt><dd class="col-sm-10"><strong>{$mail.subject|escape}</strong></dd>
        <dt class="col-sm-2">{$l10n->get('SYS_TIME')}</dt><dd class="col-sm-10">{$mail.time|escape} &middot; {$mail.mode|escape}</dd>
        {if isset($mail.from)}<dt class="col-sm-2">{$l10n->get('SYS_SENDER')}</dt><dd class="col-sm-10">{$mail.fromName|default:''|escape} &lt;{$mail.from|escape}&gt;</dd>{/if}
        {if isset($mail.to) && count($mail.to) > 0}<dt class="col-sm-2">To</dt><dd class="col-sm-10">{$mail.to|join:', '|escape}</dd>{/if}
        {if isset($mail.cc) && count($mail.cc) > 0}<dt class="col-sm-2">Cc</dt><dd class="col-sm-10">{$mail.cc|join:', '|escape}</dd>{/if}
        {if isset($mail.bcc) && count($mail.bcc) > 0}<dt class="col-sm-2">Bcc</dt><dd class="col-sm-10">{$mail.bcc|join:', '|escape}</dd>{/if}
        <dt class="col-sm-2">{$l10n->get('PLG_DEVTOOLS_MAIL_ENVELOPE')}</dt><dd class="col-sm-10">{$mail.envelope|join:', '|escape}</dd>
        {if isset($mail.originalRecipients)}<dt class="col-sm-2">{$l10n->get('PLG_DEVTOOLS_MAIL_ORIGINAL_RECIPIENTS')}</dt><dd class="col-sm-10">{$mail.originalRecipients|join:', '|escape}</dd>{/if}
        {if isset($mail.attachments) && count($mail.attachments) > 0}<dt class="col-sm-2">{$l10n->get('SYS_ATTACHMENT')}</dt><dd class="col-sm-10">{$mail.attachments|join:', '|escape}</dd>{/if}
        {if isset($mail.note)}<dt class="col-sm-2"></dt><dd class="col-sm-10 text-warning">{$mail.note|escape}</dd>{/if}
    </dl>
    {if $mail.hasRaw}<p><a class="btn btn-sm btn-outline-secondary" href="{$emlUrl}"><i class="bi bi-download"></i> .eml</a></p>{/if}
    {if isset($mail.html) && $mail.html != ''}
        <h5>HTML</h5>
        <iframe class="admidio-devtools-mail-frame mb-3" sandbox src="{$htmlUrl}" title="HTML"></iframe>
    {/if}
    {if isset($mail.text) && $mail.text != ''}
        <h5>{$l10n->get('PLG_DEVTOOLS_MAIL_TEXT')}</h5>
        <pre class="admidio-devtools-pre">{$mail.text|escape}</pre>
    {/if}
{elseif count($mails) === 0}
    <div class="alert alert-info">{$l10n->get('PLG_DEVTOOLS_MAILS_NONE')}</div>
{else}
    <form method="post" action="{$deleteUrl}" class="mb-3">
        <input type="hidden" name="adm_csrf_token" value="{$devtoolsCsrfToken}">
        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> {$l10n->get('PLG_DEVTOOLS_DELETE_ALL')}</button>
    </form>
    <input type="search" class="form-control mb-3" data-devtools-filter="adm_plg_devtools_mail_table" placeholder="{$l10n->get('PLG_DEVTOOLS_SEARCH')}">
    <div class="table-responsive">
        <table id="adm_plg_devtools_mail_table" class="table table-sm table-hover admidio-devtools-table">
            <thead><tr><th>{$l10n->get('SYS_TIME')}</th><th>{$l10n->get('SYS_SUBJECT')}</th><th>{$l10n->get('SYS_RECIPIENT')}</th><th></th></tr></thead>
            <tbody>
                {foreach $mails as $entry}
                    <tr>
                        <td class="text-nowrap"><a href="{$entry.link}">{$entry.time|escape}</a></td>
                        <td><a href="{$entry.link}">{$entry.subject|escape}</a></td>
                        <td><small>{$entry.recipients|escape}</small></td>
                        <td><small>{$entry.mode|escape}</small></td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
    </div>
{/if}
