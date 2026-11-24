<?php
namespace AdmidioPlugin\DevTools\Scaffold;

use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

/**
 ***********************************************************************************************
 * Creates the skeleton of a new plugin that follows the conventions of the example plugin
 * hello-world: manifest, entry file with the direct-call guard and an example hook, language files
 * in English and German (informal and formal), and optionally a page, a CLI command, a setting, a
 * PHPUnit test and the GitHub release workflow.
 *
 * @copyright Reinhold Kainhofer
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class PluginScaffolder
{
    /** @var array<string,mixed> */
    private array $options;

    /**
     * @param array<string,mixed> $options id, name, description, author, page, cli, setting, tests,
     *                                     releaseWorkflow
     */
    public function __construct(array $options)
    {
        $id = (string)($options['id'] ?? '');
        if (preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $id) !== 1 || strlen($id) > 40) {
            throw new InvalidArgumentException('The plugin ID must consist of lowercase letters, digits and single hyphens and start with a letter.');
        }

        $this->options = array(
            'id' => $id,
            'name' => trim((string)($options['name'] ?? '')) ?: ucwords(str_replace('-', ' ', $id)),
            'description' => trim((string)($options['description'] ?? '')) ?: 'A plugin for Admidio.',
            'author' => trim((string)($options['author'] ?? '')) ?: 'Unknown',
            'page' => !empty($options['page']),
            'cli' => !empty($options['cli']),
            'setting' => !empty($options['setting']),
            'tests' => !empty($options['tests']),
            'releaseWorkflow' => !empty($options['releaseWorkflow'])
        );
    }

    public function getId(): string
    {
        return $this->options['id'];
    }

    /** Prefix of the language keys, e.g. PLG_MY_PLUGIN for my-plugin. */
    public function getKeyPrefix(): string
    {
        return 'PLG_' . strtoupper(str_replace('-', '_', $this->options['id']));
    }

    /** Namespace of the classes, e.g. AdmidioPlugin\MyPlugin for my-plugin. */
    public function getNamespace(): string
    {
        return 'AdmidioPlugin\\' . str_replace(' ', '', ucwords(str_replace('-', ' ', $this->options['id'])));
    }

    /** Prefix of setting names, e.g. my_plugin. */
    private function getSettingPrefix(): string
    {
        return str_replace('-', '_', $this->options['id']);
    }

    /**
     * The files of the new plugin: relative path => content.
     * @return array<string,string>
     */
    public function generate(): array
    {
        $files = array(
            'plugin.json' => $this->manifest(),
            'plugin.php' => $this->entryFile(),
            'src/' . $this->className() . '.php' => $this->mainClass(),
            'languages/en.xml' => $this->languageFile('en'),
            'languages/de.xml' => $this->languageFile('de'),
            'languages/de-DE.xml' => $this->languageFile('de-DE'),
            'README.md' => $this->readme(),
            '.gitattributes' => "* text=auto eol=lf\n*.png binary\n*.jpg binary\n/.git* export-ignore\n/.github export-ignore\n/tests export-ignore\n"
        );

        if ($this->options['page']) {
            $files['modules/index.php'] = $this->page();
            $files['templates/plugin.' . $this->options['id'] . '.tpl'] = $this->template();
        }
        if ($this->options['cli']) {
            $files['cli.php'] = $this->cliFile();
        }
        if ($this->options['tests']) {
            $files['tests/Unit/ManifestTest.php'] = $this->manifestTest();
        }
        if ($this->options['releaseWorkflow']) {
            $files['.github/workflows/release.yml'] = "name: Release\n\non:\n  push:\n    tags: [ 'v*' ]\n\npermissions:\n  contents: write\n\njobs:\n  release:\n    uses: Admidio/admidio/.github/workflows/plugin-release.yml@master\n";
        }

        ksort($files, SORT_STRING);
        return $files;
    }

    /**
     * Write the plugin into a directory that must not exist yet.
     */
    public function writeTo(string $directory): void
    {
        if (file_exists($directory)) {
            throw new RuntimeException('The directory ' . $directory . ' already exists.');
        }
        foreach ($this->generate() as $path => $content) {
            $file = $directory . '/' . $path;
            if (!is_dir(dirname($file)) && !mkdir(dirname($file), 0775, true) && !is_dir(dirname($file))) {
                throw new RuntimeException('The directory ' . dirname($file) . ' cannot be created.');
            }
            if (file_put_contents($file, $content) === false) {
                throw new RuntimeException('The file ' . $file . ' cannot be written.');
            }
        }
    }

    /**
     * A ZIP archive with one folder named after the plugin ID, as the plugin manager installs it.
     * @return string The path of the temporary archive; the caller deletes it.
     */
    public function createZip(): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP extension zip is not available.');
        }
        $file = tempnam(sys_get_temp_dir(), 'plugin') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The archive cannot be created.');
        }
        foreach ($this->generate() as $path => $content) {
            $zip->addFromString($this->options['id'] . '/' . $path, $content);
        }
        $zip->close();
        return $file;
    }

    private function className(): string
    {
        return substr(strrchr('\\' . $this->getNamespace(), '\\'), 1);
    }

    private function header(string $title): string
    {
        return "/**\n"
            . " ***********************************************************************************************\n"
            . ' * ' . $this->options['name'] . ' - ' . $title . "\n"
            . " *\n"
            . ' * @copyright ' . $this->options['author'] . "\n"
            . " * @see https://www.admidio.org/\n"
            . " * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only\n"
            . " ***********************************************************************************************\n"
            . " */\n";
    }

    private function manifest(): string
    {
        $prefix = $this->getKeyPrefix();
        $manifest = array(
            'id' => $this->options['id'],
            'name' => $prefix . '_NAME',
            'description' => $prefix . '_DESCRIPTION',
            'version' => '0.1.0',
            'author' => $this->options['author'],
            'url' => 'https://www.admidio.org',
            'icon' => 'bi-puzzle',
            'requires' => array('admidio' => '>=6.0', 'php' => '>=8.2'),
            'autoload' => array($this->getNamespace() . '\\' => 'src/')
        );
        if ($this->options['setting']) {
            $manifest['settings'] = array(
                $this->getSettingPrefix() . '_greeting' => array(
                    'type' => 'string',
                    'default' => 'Hello',
                    'label' => $prefix . '_GREETING',
                    'description' => $prefix . '_GREETING_DESC'
                )
            );
        }
        return json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    private function entryFile(): string
    {
        return "<?php\n" . $this->header('entry file.') . "\n"
            . 'use Admidio\\Hooks\\Hooks;' . "\n"
            . 'use ' . $this->getNamespace() . '\\' . $this->className() . ";\n\n"
            . "/*\n * This file is included by the plugin loader and is never an entry point of its own.\n */\n"
            . "if (realpath(\$_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {\n    exit('This page may not be called directly!');\n}\n\n"
            . "// An example hook: change the headline of every page. Remove it or replace it with your own.\n"
            . "Hooks::addFilter('page_headline', array(" . $this->className() . "::class, 'decorateHeadline'), Hooks::DEFAULT_PRIORITY, 2, '" . $this->options['id'] . "');\n";
    }

    private function mainClass(): string
    {
        $greeting = $this->options['setting']
            ? "        global \$gSettingsManager;\n\n        return \$gSettingsManager->getString('" . $this->getSettingPrefix() . "_greeting');\n"
            : "        return 'Hello';\n";

        return "<?php\nnamespace " . $this->getNamespace() . ";\n\n"
            . "use Admidio\\UI\\Presenter\\PagePresenter;\n\n"
            . $this->header('main class.')
            . 'final class ' . $this->className() . "\n{\n"
            . "    public const ID = '" . $this->options['id'] . "';\n\n"
            . "    /**\n     * Callback of the **page_headline** filter. It leaves the headline as it is; change it to see the hook work.\n"
            . "     * @param string \$headline The headline of the page.\n     * @param PagePresenter \$page The page.\n     * @return string\n     */\n"
            . "    public static function decorateHeadline(string \$headline, PagePresenter \$page): string\n    {\n        return \$headline;\n    }\n\n"
            . "    /**\n     * The text the page of the plugin shows.\n     */\n"
            . "    public static function getGreeting(): string\n    {\n" . $greeting . "    }\n}\n";
    }

    private function languageFile(string $language): string
    {
        $prefix = $this->getKeyPrefix();
        $name = htmlspecialchars($this->options['name'], ENT_XML1);
        $description = htmlspecialchars($this->options['description'], ENT_XML1);

        $strings = array(
            $prefix . '_DESCRIPTION' => $description,
            $prefix . '_NAME' => $name
        );
        if ($this->options['setting']) {
            $strings[$prefix . '_GREETING'] = $language === 'en' ? 'Greeting' : 'Begrüßung';
            $strings[$prefix . '_GREETING_DESC'] = match ($language) {
                'en' => 'The text the page of the plugin shows.',
                'de' => 'Der Text, den die Seite des Plugins anzeigt.',
                default => 'Der Text, den die Seite des Plugins anzeigt.'
            };
        }
        ksort($strings, SORT_STRING);

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<resources>\n";
        foreach ($strings as $key => $text) {
            $xml .= '  <string name="' . $key . '">' . $text . "</string>\n";
        }
        return $xml . "</resources>\n";
    }

    private function page(): string
    {
        $prefix = $this->getKeyPrefix();
        return "<?php\n" . $this->header('the page of the plugin.') . "\n"
            . "use Admidio\\Infrastructure\\Plugins\\Plugin;\n"
            . "use Admidio\\Infrastructure\\Plugins\\PluginRegistry;\n"
            . "use Admidio\\UI\\Presenter\\PagePresenter;\n"
            . 'use ' . $this->getNamespace() . '\\' . $this->className() . ";\n\n"
            . "try {\n"
            . "    require_once(__DIR__ . '/../../../system/common.php');\n\n"
            . '    $plugin = PluginRegistry::get(' . $this->className() . "::ID);\n\n"
            . "    \$page = PagePresenter::withHtmlIDAndHeadline('adm_plg_" . $this->getSettingPrefix() . "', \$gL10n->get('" . $prefix . "_NAME'));\n"
            . "    \$gNavigation->addStartUrl(CURRENT_URL, \$page->getHeadline(), \$plugin->icon);\n\n"
            . "    \$page->addTemplateFolder(\$plugin->getDirectory(Plugin::DIR_TEMPLATES));\n"
            . "    \$page->addTemplateFile('plugin." . $this->options['id'] . ".tpl');\n"
            . "    \$page->assignSmartyVariable('greeting', " . $this->className() . "::getGreeting());\n"
            . "    \$page->show();\n"
            . "} catch (Throwable \$e) {\n    handleException(\$e);\n}\n";
    }

    private function template(): string
    {
        return "<p>{\$greeting|escape}</p>\n";
    }

    private function cliFile(): string
    {
        return "<?php\n" . $this->header('command-line commands.') . "\n"
            . "use Admidio\\Infrastructure\\Cli\\CliApplication;\n"
            . "use Admidio\\Infrastructure\\Cli\\CliTaskRegistry;\n\n"
            . "if (PHP_SAPI !== 'cli') {\n    exit('This page may not be called directly!');\n}\n\n"
            . "CliTaskRegistry::register(\n"
            . "    '" . $this->options['id'] . ":hello',\n"
            . "    '" . $this->options['id'] . "',\n"
            . "    static function (array \$arguments, array \$options): int {\n"
            . "        CliApplication::writeSuccess('Hello from " . addslashes($this->options['name']) . "', \$options);\n"
            . "        return 0;\n"
            . "    },\n"
            . "    'Say hello.',\n"
            . "    '" . $this->options['id'] . ":hello',\n"
            . "    array(),\n    array(),\n    array(),\n"
            . "    CliTaskRegistry::ACCESS_VISIBLE\n);\n";
    }

    private function manifestTest(): string
    {
        $testNamespace = $this->getNamespace() . '\\Tests\\Unit';
        return "<?php\nnamespace " . $testNamespace . ";\n\n"
            . "use Admidio\\Infrastructure\\Plugins\\Plugin;\n"
            . "use PHPUnit\\Framework\\TestCase;\n\n"
            . "final class ManifestTest extends TestCase\n{\n"
            . "    public function testManifestIsValid(): void\n    {\n"
            . "        \$plugin = Plugin::read(dirname(__DIR__, 2));\n\n"
            . "        \$this->assertTrue(\$plugin->isValid(), (string)\$plugin->error);\n"
            . "        \$this->assertSame('" . $this->options['id'] . "', \$plugin->id);\n"
            . "    }\n\n"
            . "    public function testLanguageFilesHaveTheSameKeys(): void\n    {\n"
            . "        \$keys = array();\n"
            . "        foreach (array('en', 'de', 'de-DE') as \$language) {\n"
            . "            preg_match_all('/<string name=\"([A-Z0-9_]+)\"/', (string)file_get_contents(dirname(__DIR__, 2) . '/languages/' . \$language . '.xml'), \$matches);\n"
            . "            \$keys[\$language] = \$matches[1];\n"
            . "        }\n"
            . "        \$this->assertSame(\$keys['en'], \$keys['de']);\n"
            . "        \$this->assertSame(\$keys['en'], \$keys['de-DE']);\n"
            . "    }\n}\n";
    }

    private function readme(): string
    {
        $text = '# ' . $this->options['name'] . "\n\n" . $this->options['description'] . "\n\n"
            . "A plugin for Admidio 6.0 and later.\n\n"
            . "## Installation\n\n"
            . "Upload the ZIP archive in *Administration → Extensions → Add plugin*, then install and enable the plugin.\n\n"
            . "## Development\n\n"
            . "* `plugin.json` – the manifest\n"
            . "* `plugin.php` – the entry file, registers the hooks\n"
            . "* `src/` – the classes, namespace `" . $this->getNamespace() . "`\n"
            . "* `languages/` – language keys with the prefix `" . $this->getKeyPrefix() . "_`\n";
        if ($this->options['page']) {
            $text .= "* `modules/index.php` and `templates/` – the page of the plugin\n";
        }
        if ($this->options['cli']) {
            $text .= "* `cli.php` – the command `" . $this->options['id'] . ":hello`\n";
        }
        if ($this->options['releaseWorkflow']) {
            $text .= "\nPushing a tag `v1.0.0` builds the archive for the plugin directory and attaches it to the GitHub release.\n";
        }
        return $text . "\n## License\n\nGNU General Public License v2.0 only.\n";
    }
}
