<?php

namespace CardanoPhp\DataClient\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Whether this package can be installed on its own and still work.
 *
 * The failure it guards is a dependency nobody meant to add: a facade, a `config()` call, a
 * helper that happens to resolve because a framework is loaded in the same process. Anything
 * of that kind works wherever it is written and is a fatal error in a project that installed
 * this package and nothing else, which means it is found by a stranger rather than by the
 * suite. Everything the package needs is a PSR interface it is handed.
 */
class PackageIsSelfContainedTest extends TestCase
{
    /**
     * Things that only exist because a framework is hosting the code.
     *
     * A framework's helpers are the dangerous half, because they resolve wherever it is loaded
     * and are a fatal error anywhere else.
     */
    private const FRAMEWORK_ONLY = [
        '/\bApp\\\\[A-Z]/' => 'an application namespace',
        '/\bIlluminate\\\\/' => 'the Laravel namespace',
        '/(?<![\w$>:])(?<!function )(base|app|config|storage|resource|database|public)_path\s*\(/' => "one of Laravel's path helpers",
        '/(?<![\w$>:])(?<!function )(config|env|app|resolve|logger|report|abort|dispatch|cache|now)\s*\(/' => 'a Laravel global helper',
        '/\b(Log|Cache|Http|Storage|Config|DB|Crypt|Str|Arr)::/' => 'a Laravel facade',
    ];

    /**
     * The root namespace of every third-party import, and the requirement that carries it.
     */
    private const VENDOR_NAMESPACES = [
        'Psr' => null,
        'PHPUnit' => 'phpunit/phpunit',
        'GuzzleHttp' => 'guzzlehttp/psr7',
    ];

    /**
     * The PSR interfaces this package imports, and the package each one comes from. Kept
     * apart from the list above because five requirements share the one `Psr` root.
     */
    private const PSR_PACKAGES = [
        'Psr\\Http\\Client\\' => 'psr/http-client',
        'Psr\\Http\\Message\\' => 'psr/http-factory',
        'Psr\\Log\\' => 'psr/log',
        'Psr\\SimpleCache\\' => 'psr/simple-cache',
    ];

    /**
     * Function prefixes that are an extension rather than the language.
     *
     * ext-json is left out on purpose. It has been compiled in and impossible to disable
     * since PHP 8.0, so requiring it says nothing and its absence is not a gap.
     */
    private const EXTENSION_PREFIXES = [
        'bc' => 'ext-bcmath',
        'mb_' => 'ext-mbstring',
        'gmp_' => 'ext-gmp',
        'openssl_' => 'ext-openssl',
        'curl_' => 'ext-curl',
        'sodium_' => 'ext-sodium',
    ];

    private static function root(): string
    {
        return dirname(__DIR__);
    }

    /**
     * A file with its comments and its string contents taken out.
     *
     * Both of those hold the names this test is hunting for, as prose and as data: this
     * file's own docblock names the helpers, and the pattern list above is a list of
     * strings spelling them out. Scanning raw text makes every such file a violation of
     * itself. Tokenising leaves the function names, class names and imports, which is where
     * a real dependency would be.
     */
    private static function codeOnly(string $source): string
    {
        $code = '';

        foreach (token_get_all($source) as $token) {
            if (is_string($token)) {
                $code .= $token;

                continue;
            }

            $code .= match ($token[0]) {
                T_COMMENT, T_DOC_COMMENT => ' ',
                T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML => "''",
                default => $token[1],
            };
        }

        return $code;
    }

    /**
     * @return array<string, string> relative path => contents
     */
    private static function phpFiles(string ...$directories): array
    {
        $files = [];

        foreach ($directories as $directory) {
            $tree = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(self::root().'/'.$directory, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($tree as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $relative = substr($file->getPathname(), strlen(self::root()) + 1);
                    $files[$relative] = (string) file_get_contents($file->getPathname());
                }
            }
        }

        ksort($files);

        return $files;
    }

    /**
     * @return array<string, mixed>
     */
    private static function manifest(): array
    {
        $contents = (string) file_get_contents(self::root().'/composer.json');

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_no_file_in_the_package_reaches_for_a_framework(): void
    {
        $found = [];

        foreach (self::phpFiles('src', 'tests') as $path => $source) {
            $code = self::codeOnly($source);

            foreach (self::FRAMEWORK_ONLY as $pattern => $what) {
                if (preg_match($pattern, $code, $match) === 1) {
                    $found[] = $path.' uses '.$what.' ('.trim($match[0]).')';
                }
            }
        }

        $this->assertSame([], $found, implode("\n", array_merge(
            ['The package leans on a framework rather than on the interfaces it declares:'],
            $found,
        )));
    }

    /**
     * Every class is where PSR-4 says it is, so the autoload root in composer.json is the
     * whole story and nothing is being found by a classmap that happened to scan the tree.
     */
    public function test_every_class_sits_where_the_autoload_root_says_it_does(): void
    {
        $wrong = [];

        foreach (self::phpFiles('src', 'tests') as $path => $source) {
            preg_match('/^namespace\s+([^;]+);/m', $source, $namespace);
            preg_match('/^(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|enum|trait)\s+(\w+)/m', $source, $name);

            $this->assertNotEmpty($namespace, $path.' declares no namespace.');
            $this->assertNotEmpty($name, $path.' declares no class, interface, enum or trait.');

            $root = str_starts_with($path, 'tests/') ? 'CardanoPhp\\DataClient\\Tests\\' : 'CardanoPhp\\DataClient\\';
            $directory = str_starts_with($path, 'tests/') ? 'tests/' : 'src/';

            $expected = $directory.str_replace('\\', '/', substr($namespace[1].'\\'.$name[1], strlen($root))).'.php';

            if ($expected !== $path) {
                $wrong[] = $path.' declares '.$namespace[1].'\\'.$name[1].', which PSR-4 puts at '.$expected;
            }
        }

        $this->assertSame([], $wrong, implode("\n", array_merge(
            ['A class is not at the path its namespace requires:'],
            $wrong,
        )));
    }

    /**
     * An import the package does not require is a package that happens to be installed
     * because something else asked for it. It disappears the moment this is installed alone.
     */
    public function test_every_third_party_import_is_a_declared_requirement(): void
    {
        $manifest = self::manifest();
        $required = array_merge($manifest['require'], $manifest['require-dev']);
        $undeclared = [];
        $seen = [];

        foreach (self::phpFiles('src', 'tests') as $path => $source) {
            preg_match_all('/^use\s+(?:function\s+)?([^\s;]+)/m', $source, $imports);

            foreach ($imports[1] as $import) {
                $root = strtok($import, '\\');

                if ($root === 'CardanoPhp') {
                    continue;
                }

                if (! str_contains($import, '\\')) {
                    // A global-namespace import is the language's own, and it either exists
                    // or it does not.
                    $this->assertTrue(
                        class_exists($import) || interface_exists($import),
                        $path.' imports '.$import.', which PHP does not define.'
                    );

                    continue;
                }

                if (! array_key_exists($root, self::VENDOR_NAMESPACES)) {
                    $undeclared[] = $path.' imports '.$import.', whose root namespace maps to no requirement';

                    continue;
                }

                $package = self::VENDOR_NAMESPACES[$root] ?? self::psrPackageFor($import);

                if ($package === null) {
                    $undeclared[] = $path.' imports '.$import.', which maps to no PSR requirement';

                    continue;
                }

                $seen[$package] = true;

                if (! isset($required[$package])) {
                    $undeclared[] = $path.' imports '.$import.', which needs '.$package;
                }
            }
        }

        $this->assertSame([], $undeclared, implode("\n", array_merge(
            ['The package imports something it does not require:'],
            $undeclared,
        )));

        // And the other direction, so the requirement list does not accumulate packages
        // nothing uses. psr/http-message is the exception: it carries the request and
        // response types the factories in psr/http-factory return, so it is required
        // without being imported by name.
        $declared = array_values(array_filter(
            array_merge(array_values(self::PSR_PACKAGES), array_filter(array_values(self::VENDOR_NAMESPACES))),
        ));

        $this->assertSame(
            [],
            array_values(array_intersect(array_diff($declared, array_keys($seen)), array_keys($required))),
            'The package requires something no file in it imports.'
        );
    }

    private static function psrPackageFor(string $import): ?string
    {
        foreach (self::PSR_PACKAGES as $prefix => $package) {
            if (str_starts_with($import, $prefix)) {
                return $package;
            }
        }

        return null;
    }

    public function test_the_extensions_the_code_calls_are_the_extensions_it_requires(): void
    {
        $required = array_keys(array_filter(
            self::manifest()['require'],
            static fn (string $package): bool => str_starts_with($package, 'ext-'),
            ARRAY_FILTER_USE_KEY,
        ));

        $used = [];

        foreach (self::phpFiles('src') as $source) {
            $code = self::codeOnly($source);

            foreach (self::EXTENSION_PREFIXES as $prefix => $extension) {
                if (preg_match('/(?<![\w$>:])(?<!function )'.preg_quote($prefix, '/').'[a-z_0-9]*\s*\(/', $code) === 1) {
                    $used[$extension] = true;
                }
            }
        }

        $used = array_keys($used);
        sort($used);
        sort($required);

        $this->assertSame($required, $used, 'The declared extensions and the ones the source calls have drifted apart.');
    }

    public function test_the_package_is_apache_licensed_and_says_so_where_a_machine_reads_it(): void
    {
        // "Apache License 2.0" is prose, not an SPDX identifier, and a tool reading the
        // manifest for a licence gets nothing from it.
        $this->assertSame('Apache-2.0', self::manifest()['license']);
        $this->assertSame('cardano-php/data-client', self::manifest()['name']);

        $this->assertFileExists(self::root().'/LICENSE');
        $this->assertFileExists(self::root().'/NOTICE');

        $this->assertStringContainsString('Apache License', (string) file_get_contents(self::root().'/LICENSE'));
        $this->assertStringContainsString('cardano-php/data-client', (string) file_get_contents(self::root().'/NOTICE'));
    }

    /**
     * The workflow is the package's own, and it has to name the three versions the
     * ecosystem's other packages already test on. A package that only proves itself on the
     * version one application happens to deploy is a package nobody else can safely install.
     */
    public function test_the_package_tests_on_every_supported_php_version(): void
    {
        $workflow = (string) file_get_contents(self::root().'/.github/workflows/phpunit-tests.yml');

        foreach (['8.2', '8.3', '8.4'] as $version) {
            $this->assertStringContainsString("'".$version."'", $workflow, 'The workflow does not test on PHP '.$version);
        }

        $this->assertStringContainsString('composer install', $workflow);
        $this->assertStringContainsString('phpunit', $workflow);
    }

    /**
     * Every fixture is a file on disk, and the suite reads them rather than a network.
     */
    public function test_the_suite_makes_no_network_call(): void
    {
        $found = [];

        foreach (self::phpFiles('src', 'tests') as $path => $source) {
            if (! str_starts_with($path, 'tests/')) {
                continue;
            }

            $code = self::codeOnly($source);

            foreach (['curl_', 'file_get_contents\s*\(\s*\$?[a-z]*url', 'fsockopen', 'stream_socket_client'] as $pattern) {
                if (preg_match('/(?<![\w$>:])'.$pattern.'/i', $code) === 1) {
                    $found[] = $path.' opens something that is not a file';
                }
            }
        }

        $this->assertSame([], $found, implode("\n", $found));
        $this->assertDirectoryExists(self::root().'/tests/fixtures/koios');
        $this->assertFileExists(self::root().'/tests/fixtures/koios/README.md');
    }
}
