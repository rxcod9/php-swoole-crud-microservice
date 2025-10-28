<?php
/**
 * preload.php
 * Purpose: Safely preload PHP files in dependency-correct order.
 * This ensures traits, interfaces, and base classes are loaded before dependents.
 *
 * Attach in php.ini:
 *     opcache.preload=/var/www/html/preload.php
 *     opcache.preload_user=www-data
 */

declare(strict_types=1);

define('VENDOR_AUTOLOAD_PATH', __DIR__ . '/vendor/autoload.php');
/**
 * Directories to preload.
 */
$dirs = [
    VENDOR_AUTOLOAD_PATH,
    __DIR__ . '/src',
    __DIR__ . '/app',
];

/**
 * --- Step 1: Load Composer Autoloader ---
 */
if (file_exists(VENDOR_AUTOLOAD_PATH)) {
    require_once VENDOR_AUTOLOAD_PATH;
}

/**
 * --- Step 2: Collect all PHP files ---
 */
$phpFiles = [];
foreach ($dirs as $dir) {
    if (is_file($dir) && str_ends_with($dir, '.php')) {
        $phpFiles[] = realpath($dir);
        continue;
    }
    if (!is_dir($dir)) {
        continue;
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($it as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $phpFiles[] = $file->getPathname();
        }
    }
}

/**
 * --- Step 3: Sort files for dependency safety ---
 * Traits, Interfaces, and Abstracts go first.
 * Concrete classes and normal files later.
 */
usort($phpFiles, static function (string $a, string $b): int {
    $aName = strtolower($a);
    $bName = strtolower($b);
    $weight = static function (string $f): int {
        return match (true) {
            str_contains($f, '/traits/') => 0,
            str_contains($f, '/interfaces/') => 1,
            str_contains($f, '/abstract') => 2,
            str_contains($f, '/base') => 3,
            default => 4,
        };
    };
    return $weight($aName) <=> $weight($bName);
});

/**
 * --- Step 4: Safely require each file ---
 */
foreach ($phpFiles as $file) {
    try {
        require_once $file;
    } catch (Throwable $e) {
        error_log(sprintf(
            "[Preload Warning] Failed loading %s: %s",
            $file,
            $e->getMessage()
        ));
    }
}

error_log('[Preload] Completed safely at ' . date('Y-m-d H:i:s'));
