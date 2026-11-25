<?php
namespace AdmidioPlugin\DevTools;

use Admidio\Hooks\Hooks;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Plugins\Plugin;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\PagePresenter;
use AdmidioPlugin\DevTools\Hooks\HookTracer;
use AdmidioPlugin\DevTools\Logs\ExceptionCatcher;
use AdmidioPlugin\DevTools\Mail\MailCatcher;
use AdmidioPlugin\DevTools\Snapshot\SnapshotConfig;
use AdmidioPlugin\DevTools\Snapshot\SnapshotStore;

/**
 ***********************************************************************************************
 * The DevTools plugin: the guard that keeps it to development installations, the registration of
 * the enabled features and what the pages have in common.
 *
 * Nothing is registered unless $gDebug is set in config.php. Every page requires an administrator.
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class DevTools
{
    public const ID = 'devtools';
    private const CORE_PAGE_EXTENSIONS = 'Admidio\\UI\\Component\\PageExtensions';

    /**
     * The pages of the plugin in the order of the navigation: file => array(text key, icon).
     */
    public const PAGES = array(
        'index.php' => array('PLG_DEVTOOLS_OVERVIEW', 'bi-tools'),
        'snapshots.php' => array('PLG_DEVTOOLS_SNAPSHOTS', 'bi-camera'),
        'hooks.php' => array('PLG_DEVTOOLS_HOOKS', 'bi-plug'),
        'traces.php' => array('PLG_DEVTOOLS_HOOK_TRACES', 'bi-activity'),
        'logs.php' => array('PLG_DEVTOOLS_LOGS', 'bi-journal-text'),
        'maintenance.php' => array('PLG_DEVTOOLS_MAINTENANCE', 'bi-wrench-adjustable'),
        'console.php' => array('PLG_DEVTOOLS_CONSOLE', 'bi-terminal'),
        'mails.php' => array('PLG_DEVTOOLS_MAILS', 'bi-envelope'),
        'preferences.php' => array('PLG_DEVTOOLS_PREFERENCES_DIFF', 'bi-sliders'),
        'scaffold.php' => array('PLG_DEVTOOLS_SCAFFOLD', 'bi-box-seam')
    );

    /**
     * Whether this installation is marked as a development installation ($gDebug in config.php).
     */
    public static function isDevelopmentInstallation(): bool
    {
        return !empty($GLOBALS['gDebug']);
    }

    /**
     * Register what the enabled features contribute. Called by plugin.php.
     */
    public static function register(): void
    {
        global $gSettingsManager;

        if (!self::isDevelopmentInstallation()) {
            return;
        }

        // only administrators see the menu entry
        Hooks::addFilter('menu_item_visible', static function (bool $visible, int $menuId, string $componentName): bool {
            return $componentName === self::ID ? $visible && self::isAdministrator() : $visible;
        }, Hooks::DEFAULT_PRIORITY, 3, self::ID . '-menu');

        // The badge in the header bar: through the filter of the core where it has one, otherwise
        // moved into the header bar in the browser.
        Hooks::addAction('page_before_render', array(self::class, 'addStyles'), Hooks::DEFAULT_PRIORITY, 1, self::ID . '-styles');
        if (class_exists(self::CORE_PAGE_EXTENSIONS)) {
            Hooks::addFilter('page_header_items', array(self::class, 'addHeaderItems'), Hooks::DEFAULT_PRIORITY, 1, self::ID . '-header');
        } else {
            Hooks::addAction('page_before_render', array(self::class, 'decoratePage'), Hooks::DEFAULT_PRIORITY, 1, self::ID . '-header');
        }

        if ($gSettingsManager->getBool('devtools_exception_catcher')) {
            Hooks::addAction('exception_terminating', array(ExceptionCatcher::class, 'store'), Hooks::DEFAULT_PRIORITY, 2, self::ID . '-exceptions');
        }

        if ($gSettingsManager->getString('devtools_mail_mode') !== MailCatcher::MODE_OFF) {
            // late, so that the recipients other plugins decide on are the ones that are caught
            Hooks::addFilter('email_recipients', array(MailCatcher::class, 'filterRecipients'), 1000, 2, self::ID . '-mail');
        }

        if ($gSettingsManager->getBool('devtools_hook_trace') && PHP_SAPI !== 'cli') {
            Hooks::addAction('plugins_loaded', array(HookTracer::class, 'install'), 10000, 0, self::ID . '-trace');
        }
    }

    public static function isAdministrator(): bool
    {
        global $gCurrentUser, $gValidLogin;

        return !empty($gValidLogin) && $gCurrentUser !== null && $gCurrentUser->isAdministrator();
    }

    /**
     * Refuse a page unless this is a development installation and the user an administrator.
     * @throws Exception
     */
    public static function requireAccess(): void
    {
        if (!self::isDevelopmentInstallation()) {
            throw new Exception('PLG_DEVTOOLS_NO_DEBUG');
        }
        if (!self::isAdministrator()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
    }

    public static function getPlugin(): Plugin
    {
        return PluginRegistry::get(self::ID);
    }

    public static function url(string $page, array $params = array()): string
    {
        return SecurityUtils::encodeUrl(self::getPlugin()->getUrl($page), $params);
    }

    /**
     * The directory of the data of this plugin: caught mails, exceptions and hook traces.
     */
    public static function dataPath(string $subfolder = ''): string
    {
        $path = ADMIDIO_PATH . FOLDER_DATA . '/devtools' . ($subfolder !== '' ? '/' . $subfolder : '');
        if (!is_dir($path)) {
            @mkdir($path, 0770, true);
        }
        return $path;
    }

    public static function getSnapshotStore(): SnapshotStore
    {
        return new SnapshotStore(SnapshotConfig::load(ADMIDIO_PATH));
    }

    /**
     * Remember a message for the next page of the plugin (after a redirect).
     * @param string $type success, info, warning or danger
     */
    public static function flash(string $type, string $text): void
    {
        $_SESSION['devtools_flash'][] = array('type' => $type, 'text' => $text);
    }

    /**
     * Validate the CSRF token of a POST request of a page of the plugin.
     * @throws Exception
     */
    public static function requirePost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
        SecurityUtils::validateCsrfToken((string)($_POST['adm_csrf_token'] ?? ''));
    }

    /**
     * A page of the plugin with its navigation.
     * @param string $page File name of the page, one of PAGES.
     * @param string $template The template of the page content.
     * @throws Exception|\Smarty\Exception
     */
    public static function createPage(string $page, string $template): PagePresenter
    {
        global $gL10n, $gNavigation;

        $plugin = self::getPlugin();
        $headline = $gL10n->get('PLG_DEVTOOLS_NAME') . ' - ' . $gL10n->get(self::PAGES[$page][0]);

        $presenter = PagePresenter::withHtmlIDAndHeadline('adm_plg_devtools_' . basename($page, '.php'), $headline);
        if ($page === 'index.php') {
            $gNavigation->addStartUrl(CURRENT_URL, $headline, $plugin->icon);
        } else {
            $gNavigation->addUrl(CURRENT_URL, $headline);
        }

        $navigation = array();
        foreach (self::PAGES as $file => $definition) {
            $navigation[] = array(
                'url' => $plugin->getUrl($file),
                'label' => $gL10n->get($definition[0]),
                'icon' => $definition[1],
                'active' => $file === $page
            );
        }

        $presenter->addTemplateFolder($plugin->getDirectory(Plugin::DIR_TEMPLATES));
        $presenter->addCssFile($plugin->getAssetUrl('devtools.css'));
        $presenter->assignSmartyVariable('devtoolsNavigation', $navigation);
        $presenter->assignSmartyVariable('devtoolsFlash', $_SESSION['devtools_flash'] ?? array());
        $presenter->assignSmartyVariable('devtoolsCsrfToken', $GLOBALS['gCurrentSession']->getCsrfToken());
        unset($_SESSION['devtools_flash']);
        $presenter->addJavascriptFile($plugin->getAssetUrl('devtools.js'));
        $presenter->addTemplateFile($template);
        return $presenter;
    }

    /**
     * A duration as "3 d 4 h", "2 h 5 min" or "40 s".
     */
    public static function formatDuration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        if ($seconds >= 86400) {
            return intdiv($seconds, 86400) . ' d ' . intdiv($seconds % 86400, 3600) . ' h';
        }
        if ($seconds >= 3600) {
            return intdiv($seconds, 3600) . ' h ' . intdiv($seconds % 3600, 60) . ' min';
        }
        if ($seconds >= 60) {
            return intdiv($seconds, 60) . ' min';
        }
        return $seconds . ' s';
    }

    /**
     * Change a setting of the plugin in the current organization, from a page of the plugin.
     * @param string $name A setting of plugin.json.
     */
    public static function saveSetting(string $name, string $value): void
    {
        global $gSettingsManager;

        if (!array_key_exists($name, self::getPlugin()->settings)) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
        $gSettingsManager->set($name, $value);
    }

    /**
     * The badge and the mail catcher state, as items of the header bar.
     * @return array<int,array<string,mixed>>
     */
    private static function getBadges(): array
    {
        global $gL10n, $gSettingsManager;

        $badges = array(array(
            'id' => self::ID,
            'label' => $gL10n->get('PLG_DEVTOOLS_BADGE'),
            'icon' => 'bi-tools',
            'tooltip' => $gL10n->get('PLG_DEVTOOLS_BADGE_DESC'),
            'url' => self::isAdministrator() ? self::getPlugin()->getUrl('index.php') : '',
            // own colors: the theme makes the text of every .btn white
            'class' => 'admidio-devtools-badge',
            'sequence' => 10
        ));
        $mode = $gSettingsManager->getString('devtools_mail_mode');
        if ($mode !== MailCatcher::MODE_OFF) {
            $badges[] = array(
                'id' => self::ID . '-mail',
                'label' => '',
                'icon' => $mode === MailCatcher::MODE_CATCH ? 'bi-envelope-slash' : 'bi-envelope-arrow-up',
                'tooltip' => $gL10n->get('PLG_DEVTOOLS_MAIL_MODE_' . strtoupper($mode)),
                'url' => self::isAdministrator() ? self::getPlugin()->getUrl('mails.php') : '',
                'class' => 'admidio-devtools-badge-mail',
                'sequence' => 11
            );
        }
        return $badges;
    }

    /**
     * Callback of **page_before_render**: the styles of the badges, which are on every page.
     */
    public static function addStyles(PagePresenter $page): void
    {
        $page->addCssFile(self::getPlugin()->getAssetUrl('devtools.css'));
    }

    /**
     * Callback of **page_header_items**.
     * @param array<int,array<string,mixed>> $items
     * @return array<int,array<string,mixed>>
     */
    public static function addHeaderItems(array $items): array
    {
        return array_merge($items, self::getBadges());
    }

    /**
     * Callback of **page_before_render** for a core without page_header_items: the badges, moved into
     * the header bar in the browser. Content loaded into a dialog or a reduced page is left alone.
     */
    public static function decoratePage(PagePresenter $page): void
    {
        global $gLayoutReduced;

        if (!empty($gLayoutReduced) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') {
            return;
        }

        $page->addHtml(self::getPlugin()->renderTemplate($page, 'plugin.devtools.header.tpl', array('devtoolsBadges' => self::getBadges())));
        $page->addJavascript('
            var devtoolsHeader = $("#adm_plg_devtools_header");
            var devtoolsTarget = $("#adm_main_navbar .ms-auto").first();
            if (devtoolsTarget.length) { devtoolsHeader.prependTo(devtoolsTarget); }
            else { devtoolsHeader.insertAfter($("#adm_main_navbar .navbar-brand").first()); }
            devtoolsHeader.removeClass("d-none");', true);
    }
}
