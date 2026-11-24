<?php
namespace AdmidioPlugin\DevTools\Tests\Unit;

use Admidio\Infrastructure\Plugins\Plugin;
use AdmidioPlugin\DevTools\Scaffold\PluginScaffolder;
use AdmidioPlugin\DevTools\Snapshot\SnapshotStore;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Support/autoload.php';

final class PluginScaffolderTest extends TestCase
{
    public function testGeneratedPluginIsValid(): void
    {
        $directory = sys_get_temp_dir() . '/devtools-scaffold-' . bin2hex(random_bytes(4)) . '/my-test-plugin';
        $scaffolder = new PluginScaffolder(array(
            'id' => 'my-test-plugin', 'name' => 'My Test <Plugin>', 'author' => 'Tester',
            'page' => true, 'cli' => true, 'setting' => true, 'tests' => true, 'releaseWorkflow' => true
        ));
        $scaffolder->writeTo($directory);

        try {
            $plugin = Plugin::read($directory);
            $this->assertTrue($plugin->isValid(), (string)$plugin->error);
            $this->assertSame('my-test-plugin', $plugin->id);
            $this->assertContains('index.php', $plugin->getPages());
            $this->assertSame('AdmidioPlugin\\MyTestPlugin', $scaffolder->getNamespace());
            $this->assertSame('PLG_MY_TEST_PLUGIN', $scaffolder->getKeyPrefix());
            $this->assertFileExists($directory . '/src/MyTestPlugin.php');
            $this->assertFileExists($directory . '/.github/workflows/release.yml');

            foreach (array('en', 'de', 'de-DE') as $language) {
                $this->assertNotFalse(simplexml_load_file($directory . '/languages/' . $language . '.xml'));
            }
            foreach (array('plugin.php', 'cli.php', 'modules/index.php', 'src/MyTestPlugin.php', 'tests/Unit/ManifestTest.php') as $file) {
                exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($directory . '/' . $file) . ' 2>&1', $output, $exitCode);
                $this->assertSame(0, $exitCode, implode("\n", $output));
            }

            $this->expectException(\RuntimeException::class);
            $scaffolder->writeTo($directory);
        } finally {
            SnapshotStore::removeTree(dirname($directory));
        }
    }

    public function testInvalidIdIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PluginScaffolder(array('id' => 'My_Plugin'));
    }
}
