<ul class="nav nav-pills flex-wrap admidio-devtools-nav">
    {foreach $devtoolsNavigation as $item}
        <li class="nav-item">
            <a class="nav-link{if $item.active} active{/if}" href="{$item.url}"><i class="bi {$item.icon}"></i> {$item.label|escape}</a>
        </li>
    {/foreach}
</ul>
{foreach $devtoolsFlash as $flash}
    <div class="alert alert-{$flash.type}">{$flash.text|escape|nl2br}</div>
{/foreach}
