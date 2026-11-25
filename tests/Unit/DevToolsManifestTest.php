<?php
namespace AdmidioPlugin\DevTools\Tests\Unit;

use Admidio\Infrastructure\Plugins\Plugin;
use PHPUnit\Framework\TestCase;

final class DevToolsManifestTest extends TestCase
{
    public function testManifestUsesTheNativePluginContract(): void
    {
        $plugin = Plugin::read(dirname(__DIR__, 2));

        $this->assertTrue($plugin->isValid(), (string)$plugin->error);
        $this->assertSame('devtools', $plugin->id);
        foreach (array('index.php', 'snapshots.php', 'restore.php', 'hooks.php', 'traces.php', 'logs.php', 'maintenance.php', 'console.php', 'mails.php', 'preferences.php', 'scaffold.php') as $page) {
            $this->assertContains($page, $plugin->getPages());
        }
        $this->assertSame('catch', $plugin->settings['devtools_mail_mode']['default'] ?? null);
        $this->assertFalse($plugin->settings['devtools_hook_trace']['default'] ?? null);
    }

    /**
     * Every language key the plugin uses exists in all three language files, the files have the
     * same keys, and every file is sorted.
     */
    public function testLanguageFilesAreComplete(): void
    {
        $root = dirname(__DIR__, 2);
        $files = array();
        foreach (array('en', 'de', 'de-DE') as $language) {
            $content = (string)file_get_contents($root . '/languages/' . $language . '.xml');
            $this->assertNotFalse(simplexml_load_string($content), $language . '.xml is not well-formed');
            preg_match_all('/<string name="([A-Z0-9_]+)"/', $content, $matches);
            $files[$language] = $matches[1];
        }
        $this->assertSame($files['en'], $files['de']);
        $this->assertSame($files['en'], $files['de-DE']);
        $sorted = $files['en'];
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $files['en']);

        $used = array();
        foreach (array('modules', 'src', 'templates') as $directory) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $directory, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                preg_match_all('/PLG_DEVTOOLS_[A-Z0-9_]*[A-Z0-9]/', (string)file_get_contents($file->getPathname()), $matches);
                $used = array_merge($used, $matches[0]);
            }
        }
        // the description of every page is built from its label
        foreach (array('OVERVIEW', 'SNAPSHOTS', 'HOOKS', 'HOOK_TRACES', 'LOGS', 'MAINTENANCE', 'CONSOLE', 'MAILS', 'PREFERENCES_DIFF', 'SCAFFOLD') as $page) {
            $used[] = 'PLG_DEVTOOLS_' . $page . '_DESC';
        }
        $missing = array_diff(array_unique($used), $files['en']);
        $this->assertSame(array(), array_values($missing));
    }
}
