<?php

/**
 * Adds or merges a file-level PHPDoc block with project metadata.
 * Inserts doc **before** declare(strict_types=1) and sets correct @category and @package.
 *
 * PHP version 8.4
 *
 * @category  RectorRules
 * @package   RectorRules
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */

declare(strict_types=1);

namespace RectorRules;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Stmt\Declare_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use RuntimeException;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;

final class AddFileDocBeforeDeclareRector extends AbstractRector
{
    private array $composerMeta = [];

    public function __construct()
    {
        $composerFile = getcwd() . '/composer.json';
        if (file_exists($composerFile)) {
            $json = json_decode(file_get_contents($composerFile), true);
            if (is_array($json)) {
                $this->composerMeta = $json;
            }
        }
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add a file-level docblock before declare(strict_types=1) with correct @category and @package',
            [
                new CodeSample(
                    <<<'CODE'
<?php

declare(strict_types=1);

namespace App\Services;

class UserService {}
CODE,
                    <<<'CODE'
<?php
/**
 * src/Services/UserService.php
 *
 * Project: my-project
 * Description: Handles user operations
 *
 * PHP version 8.4
 *
 * @category  Services
 * @package   App\Services
 * @author    John Doe <john@example.com>
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
declare(strict_types=1);

namespace App\Services;

class UserService {}
CODE
                )
            ]
        );
    }

    public function getNodeTypes(): array
    {
        return [Declare_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof Declare_) {
            return null;
        }

        $filePath     = $this->file->getFilePath();
        $relativePath = str_replace(getcwd() . DIRECTORY_SEPARATOR, '', $filePath);

        $projectName        = $this->composerMeta['name'] ?? 'Unknown Project';
        $projectDescription = $this->composerMeta['description'] ?? '';
        $license            = $this->composerMeta['license'] ?? '';
        $version            = $this->composerMeta['version'] ?? '1.0.0';
        $authors            = $this->composerMeta['authors'] ?? [];
        $authorStr          = $this->getAuthorsString($authors);
        $copyrightStr       = "Copyright (c) " . date('Y');
        $generated          = date('Y-m-d');
        $link               = "https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/" . $relativePath;

        $namespace = $this->resolveNamespaceFromFile($filePath);
        $category  = $this->computeCategoryFromNamespace($namespace);

        $fileDoc = <<<PHPDOC
/**
 * {$relativePath}
 *
 * Project: {$projectName}
 * Description: {$projectDescription}
 *
 * PHP version 8.4
 *
 * @category  {$category}
 * @package   {$namespace}
 * @author    {$authorStr}
 * @copyright {$copyrightStr}
 * @license   {$license}
 * @version   {$version}
 * @since     {$generated}
 * @link      {$link}
 */
PHPDOC;

        $existingDoc = $node->getDocComment();
        $mergedDoc   = $this->mergeDocComment($existingDoc, $fileDoc);

        // Ensure correct whitespace before declare
        $node->setAttribute('comments', [$mergedDoc]);

        return $node;
    }

    private function getAuthorsString(array $authors): string
    {
        $lines = [];
        foreach ($authors as $author) {
            $lines[] = ($author['name'] ?? 'Unknown Author')
                . (isset($author['email']) ? " <{$author['email']}>" : '');
        }
        return implode(', ', $lines) ?: 'Unknown';
    }

    private function computeCategoryFromNamespace(string $namespace): string
    {
        $parts = explode('\\', $namespace);
        return $parts[1] ?? 'General';
    }

    private function resolveNamespaceFromFile(string $filePath): string
    {
        $content = file_get_contents($filePath);
        if (preg_match('/^namespace\s+([^\s;]+);/m', $content, $matches)) {
            return $matches[1];
        }
        return 'Global';
    }

    private function mergeDocComment(?Doc $existing, string $newDoc): Doc
    {
        $tagOrder = [
            'category',
            'package',
            'author',
            'copyright',
            'license',
            'version',
            'since',
            'link',
        ];

        // Determine indentation from existing doc or default 4 spaces
        $indent = '    ';
        if ($existing !== null) {
            if (preg_match('/^(\s*)\/\*\*/', $existing->getText(), $matches)) {
                $indent = $matches[1];
            }
        }

        $parseDoc = function (string $doc): array {
            $lines = preg_split('/\R/', trim($doc, "/* \n\t"));
            $desc = [];
            $tags = [];
            foreach ($lines as $line) {
                $line = trim(ltrim($line, "* \t"));
                if ($line === '') continue;

                if (str_starts_with($line, '@')) {
                    preg_match('/^@+(\S+)\s*(.*)$/', $line, $matches);
                    $tag = strtolower($matches[1] ?? '');
                    $value = $matches[2] ?? '';
                    if ($tag) {
                        $tags[$tag][] = $value;
                    }
                } else {
                    $desc[] = $line;
                }
            }
            return [$desc, $tags];
        };

        [$existingDesc, $existingTags] = $parseDoc($existing?->getText() ?? '');
        [$newDesc, $newTags] = $parseDoc($newDoc);

        // Preserve existing description
        $finalDesc = $existingDesc ?: $newDesc;

        // Merge tags: keep existing, add missing from new
        $finalTags = $existingTags;
        foreach ($newTags as $tag => $values) {
            if (!isset($finalTags[$tag])) {
                $finalTags[$tag] = $values;
            }
        }

        // Compute max tag length for alignment
        $allTags = array_merge(array_keys($finalTags), $tagOrder);
        $maxTagLen = max(array_map('strlen', $allTags));

        // Rebuild doc
        $merged = "{$indent}/**\n";
        foreach ($finalDesc as $line) {
            $merged .= "{$indent} * {$line}\n";
        }
        if ($finalDesc && $finalTags) {
            $merged .= "{$indent} *\n";
        }

        // Append tags in PHPCS order with aligned values
        foreach ($tagOrder as $tag) {
            if (!isset($finalTags[$tag])) continue;
            foreach ($finalTags[$tag] as $value) {
                $padding = str_repeat(' ', $maxTagLen - strlen($tag) + 1);
                $merged .= "{$indent} * @{$tag}{$padding}{$value}\n";
            }
            unset($finalTags[$tag]);
        }

        // Append any remaining tags
        foreach ($finalTags as $tag => $values) {
            foreach ($values as $value) {
                $padding = str_repeat(' ', $maxTagLen - strlen($tag) + 1);
                $merged .= "{$indent} * @{$tag}{$padding}{$value}\n";
            }
        }

        $merged .= "{$indent} */";

        return new Doc($merged);
    }
}
