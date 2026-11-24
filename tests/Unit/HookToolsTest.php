<?php
namespace AdmidioPlugin\DevTools\Tests\Unit;

use Admidio\Hooks\Hooks;
use AdmidioPlugin\DevTools\Hooks\DispatchSiteScanner;
use AdmidioPlugin\DevTools\Hooks\HookRegistry;
use AdmidioPlugin\DevTools\Hooks\HookTracer;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/autoload.php';

final class HookToolsTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!defined('ADMIDIO_PATH')) {
            define('ADMIDIO_PATH', dirname(__DIR__, 4));
        }
        if (!defined('FOLDER_PLUGINS')) {
            define('FOLDER_PLUGINS', '/plugins');
        }
    }

    protected function tearDown(): void
    {
        Hooks::reset('devtools_test_filter');
        Hooks::reset('devtools_test_action');
    }

    public function testRegistryListsRegistrationsInCallOrder(): void
    {
        Hooks::addFilter('devtools_test_filter', static fn(string $value): string => $value . 'b', 20, 1, 'second');
        Hooks::addFilter('devtools_test_filter', array(self::class, 'appendA'), 10, 1);

        $rows = array_values(array_filter(HookRegistry::getRegistrations(), static fn(array $row): bool => $row['hook'] === 'devtools_test_filter'));

        $this->assertCount(2, $rows);
        $this->assertSame(self::class . '::appendA', $rows[0]['callback']);
        $this->assertSame('{closure} in ' . self::class, $rows[1]['callback']);
        $this->assertSame('id:second', $rows[1]['key']);
        $this->assertSame(__FILE__, $rows[1]['file']);
        $this->assertSame('devtools', $rows[1]['owner'], 'the owner is the plugin whose directory holds the file');
        $this->assertSame('core', HookRegistry::ownerOfFile(ADMIDIO_PATH . '/src/Hooks/Hooks.php'));
    }

    public function testTracerWrapsCallbacksWithoutChangingTheResult(): void
    {
        Hooks::addFilter('devtools_test_filter', array(self::class, 'appendA'), 10, 1);
        Hooks::addAction('devtools_test_action', static function (): void {
        }, 10, 0, 'noop');

        $_SERVER['SCRIPT_FILENAME'] = '/var/www/html/modules/overview.php';
        HookTracer::install();

        $this->assertSame('xa', Hooks::applyFilters('devtools_test_filter', 'x'));
        Hooks::doAction('devtools_test_action', 1, 2);
        // removal by ID still works on a wrapped registration
        $this->assertTrue(Hooks::removeAction('devtools_test_action', 'noop'));

        $calls = (new \ReflectionProperty(HookTracer::class, 'calls'))->getValue();
        $filterCall = array_values(array_filter($calls, static fn(array $call): bool => $call['hook'] === 'devtools_test_filter'))[0];
        $this->assertTrue($filterCall['changed']);
        $this->assertSame('"x"', $filterCall['before']);
        $this->assertSame('"xa"', $filterCall['after']);

        $rows = array_values(array_filter(HookRegistry::getRegistrations(), static fn(array $row): bool => $row['hook'] === 'devtools_test_filter'));
        $this->assertSame(self::class . '::appendA', $rows[0]['callback'], 'the inspector shows the original callback');

        // nothing of the test run is written as a trace
        (new \ReflectionProperty(HookTracer::class, 'calls'))->setValue(null, array());
    }

    public function testScannerFindsCoreDispatchSites(): void
    {
        $scanner = new DispatchSiteScanner(ADMIDIO_PATH);
        $hooks = array_column(array_filter($scanner->getSites(), static fn(array $site): bool => !$site['dynamic']), 'hook');

        $this->assertContains('page_before_render', $hooks);
        $this->assertContains('email_recipients', $hooks);

        $warnings = $scanner->getWarnings(array(
            array('hook' => 'page_before_render', 'type' => 'action', 'owner' => 'core'),
            array('hook' => 'no_such_hook_anywhere', 'type' => 'action', 'owner' => 'example'),
            array('hook' => 'list_row_actions', 'type' => 'filter', 'owner' => 'a'),
            array('hook' => 'list_row_actions', 'type' => 'filter', 'owner' => 'b')
        ));
        $keys = array_column($warnings, 'key');
        $this->assertContains('PLG_DEVTOOLS_HOOK_WARNING_NOT_DISPATCHED', $keys);
        $this->assertContains('PLG_DEVTOOLS_HOOK_WARNING_SHARED_FILTER', $keys);
        $notDispatched = array_filter($warnings, static fn(array $w): bool => $w['key'] === 'PLG_DEVTOOLS_HOOK_WARNING_NOT_DISPATCHED');
        $this->assertSame(array('no_such_hook_anywhere'), array_values(array_map(static fn(array $w): string => $w['params'][0], $notDispatched)),
            'list_row_actions is dispatched through a constant and must not be reported');
    }

    public static function appendA(string $value): string
    {
        return $value . 'a';
    }
}
