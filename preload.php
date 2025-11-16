<?php
/**
 * preload.php
 * Purpose: Warm OPcache by compiling PHP files in dependency-aware order,
 *          WITHOUT executing them (no require/include).
 *
 * Attach in php.ini:
 *     opcache.preload=/var/www/html/preload.php
 *     opcache.preload_user=www-data
 */

declare(strict_types=1);

define('VENDOR_AUTOLOAD_PATH', __DIR__ . '/vendor/autoload.php');

/**
 * Directories to scan for PHP files to precompile.
 *
 * NOTE:
 * - We do NOT require anything here.
 * - We only call opcache_compile_file() to precompile into OPcache.
 */
$dirs = [
    VENDOR_AUTOLOAD_PATH,        // single file, will be compiled but not executed
    __DIR__ . '/src',
    __DIR__ . '/app',
];

/**
 * --- Step 0: Sanity check: OPcache compile API availability ---
 *
 * If the opcache_compile_file() function is not available, there is
 * no point continuing. This can happen if OPcache is not loaded for
 * this SAPI or was disabled.
 */
if (!function_exists('opcache_compile_file')) {
    error_log('[Preload Warmup] opcache_compile_file() not available. Aborting warmup.');
    return;
}

/**
 * --- Step 1: Collect all PHP files to compile ---
 *
 * @var string[] $phpFiles
 */
$phpFiles = [];

foreach ($dirs as $dir) {
    // If it's a single PHP file path (e.g., vendor/autoload.php)
    if (is_file($dir) && str_ends_with($dir, '.php')) {
        $real = realpath($dir);
        if ($real !== false) {
            $phpFiles[] = $real;
        } else {
            $phpFiles[] = $dir;
        }
        continue;
    }

    // Skip if not a directory
    if (!is_dir($dir)) {
        continue;
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    /** @var SplFileInfo $file */
    foreach ($it as $file) {
        if (!$file->isFile()) {
            continue;
        }

        if ($file->getExtension() !== 'php') {
            continue;
        }

        $path = $file->getPathname();

        // Optional: skip tests, stubs, etc. to save memory and compile time.
        if (str_contains($path, '/tests/')
            || str_contains($path, '/Test/')
            || str_contains($path, '/stubs/')
        ) {
            continue;
        }

        $phpFiles[] = $path;
    }
}

/**
 * --- Step 2: Sort files for dependency safety (still helps later) ---
 *
 * Even though we are only compiling and not executing these files,
 * it is still useful to place traits, interfaces, and base/abstract
 * code first to keep a predictable order and debugability.
 */
usort($phpFiles, static function (string $a, string $b): int {
    $aName = strtolower($a);
    $bName = strtolower($b);

    $weight = static function (string $f): int {
        return match (true) {
            str_contains($f, '/traits/')     => 0,
            str_contains($f, '/interfaces/') => 1,
            str_contains($f, '/abstract')    => 2,
            str_contains($f, '/base')        => 3,
            default                          => 4,
        };
    };

    return $weight($aName) <=> $weight($bName);
});

/**
 * --- Step 3: Precompile each file into OPcache, without executing it ---
 *
 * We use opcache_compile_file() instead of require_once.
 * This compiles the file into OPcache so that the first real require/include
 * from normal requests is served from precompiled bytecode.
 */
$compiledCount = 0;

foreach ($phpFiles as $file) {
    try {
        // @phpstan-ignore-next-line - opcache_* is provided by extension
        $result = opcache_compile_file($file);

        if ($result === false) {
            error_log(sprintf(
                '[Preload Warmup Warning] Failed to compile %s into OPcache.',
                $file
            ));
            continue;
        }

        $compiledCount++;
    } catch (Throwable $e) {
        error_log(sprintf(
            '[Preload Warmup Warning] Exception while compiling %s: %s',
            $file,
            $e->getMessage()
        ));
    }
}

error_log(sprintf(
    '[Preload Warmup] Completed. Compiled %d file(s) into OPcache at %s',
    $compiledCount,
    date('Y-m-d H:i:s')
));
