<?php

/**
 * Adds or merges a file-level PHPDoc block with project metadata.
 * Inserts doc **before** declare(strict_types=1) and sets correct @category and @package.
 *
 * PHP version 8.5
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
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;

/**
 * Rector Rule: Adds file-level PHPDoc with metadata above declare(strict_types=1)
 */
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
 * PHP version 8.5
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

    /**
     * Refactor the file by injecting a file-level PHPDoc before declare(strict_types=1)
     *
     * @param Node $node
     * @return Node|null
     */
    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof Declare_) {
            return null;
        }

        $filePath     = $this->file->getFilePath();
        $relativePath = str_replace(getcwd() . DIRECTORY_SEPARATOR, '', $filePath);

        $meta = $this->buildMetadata($filePath, $relativePath);

        // Create new file-level doc
        $fileDoc = $this->buildFileDoc($meta, $relativePath);

        // Merge existing doc (if any)
        $existingDoc = $node->getDocComment();
        $mergedDoc   = $this->mergeDocComment($existingDoc, $fileDoc);

        // Insert before declare(strict_types=1)
        $node->setAttribute('comments', [$mergedDoc]);
        return $node;
    }

    /**
     * Build project metadata from composer.json and file path
     */
    private function buildMetadata(string $filePath, string $relativePath): array
    {
        $namespace = $this->resolveNamespaceFromFile($filePath);

        return [
            'project'     => $this->composerMeta['name'] ?? 'Unknown Project',
            'description' => $this->composerMeta['description'] ?? '',
            'license'     => $this->composerMeta['license'] ?? 'MIT',
            'version'     => $this->composerMeta['version'] ?? '1.0.0',
            'authors'     => $this->getAuthorsString($this->composerMeta['authors'] ?? []),
            'category'    => $this->computeCategoryFromNamespace($namespace),
            'namespace'   => $namespace,
            'copyright'   => "Copyright (c) " . date('Y'),
            'since'       => date('Y-m-d'),
            'link'        => "https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/" . $relativePath,
        ];
    }

    /**
     * Generate the base PHPDoc string for the file.
     */
    private function buildFileDoc(array $meta, string $relativePath): string
    {
        return <<<PHPDOC
/**
 * {$relativePath}
 *
 * Project: {$meta['project']}
 * Description: {$meta['description']}
 *
 * PHP version 8.5
 *
 * @category  {$meta['category']}
 * @package   {$meta['namespace']}
 * @author    {$meta['authors']}
 * @copyright {$meta['copyright']}
 * @license   {$meta['license']}
 * @version   {$meta['version']}
 * @since     {$meta['since']}
 * @link      {$meta['link']}
 */
PHPDOC;
    }

    /**
     * Convert authors array to formatted string
     */
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

    /**
     * Merges existing and new doc comments while preserving order and formatting.
     */
    private function mergeDocComment(?Doc $existing, string $newDoc): Doc
    {
        $tagOrder = ['category', 'package', 'author', 'copyright', 'license', 'version', 'since', 'link'];
        $indent   = $this->detectIndent($existing);

        [$existingDesc, $existingTags] = $this->parseDoc($existing?->getText() ?? '');
        [$newDesc, $newTags]           = $this->parseDoc($newDoc);

        $finalDesc = $existingDesc ?: $newDesc;
        $finalTags = $this->mergeTags($existingTags, $newTags);

        return new Doc($this->formatDoc($finalDesc, $finalTags, $tagOrder, $indent));
    }

    /**
     * Detects indentation from an existing docblock or defaults to 4 spaces.
     */
    private function detectIndent(?Doc $doc): string
    {
        if ($doc && preg_match('/^(\s*)\/\*\*/', $doc->getText(), $matches)) {
            return $matches[1];
        }
        return '    ';
    }

    /**
     * Parses a PHPDoc block into description lines and tags.
     */
    private function parseDoc(string $doc): array
    {
        $lines = preg_split('/\R/', trim($doc, "/* \n\t"));
        $desc  = [];
        $tags  = [];

        foreach ($lines as $line) {
            $line = trim(ltrim($line, "* \t"));
            if ($line === '') {
                continue;
            }
            if (str_starts_with($line, '@')) {
                if (preg_match('/^@+(\S+)\s*(.*)$/', $line, $matches)) {
                    $tag = strtolower($matches[1]);
                    $value = $matches[2] ?? '';
                    $tags[$tag][] = $value;
                }
            } else {
                $desc[] = $line;
            }
        }

        return [$desc, $tags];
    }

    /**
     * Merge new tags with existing tags, without overriding existing ones.
     */
    private function mergeTags(array $existingTags, array $newTags): array
    {
        foreach ($newTags as $tag => $values) {
            if (!isset($existingTags[$tag])) {
                $existingTags[$tag] = $values;
            }
        }
        return $existingTags;
    }

    /**
     * Format the final docblock text with proper alignment and tag ordering.
     */
    private function formatDoc(array $desc, array $tags, array $order, string $indent): string
    {
        $allTags = array_merge(array_keys($tags), $order);
        $maxTagLen = max(array_map('strlen', $allTags));

        $out = "{$indent}/**\n";
        foreach ($desc as $line) {
            $out .= "{$indent} * {$line}\n";
        }

        if ($desc && $tags) {
            $out .= "{$indent} *\n";
        }

        // Ordered tags
        foreach ($order as $tag) {
            if (!isset($tags[$tag])) continue;
            foreach ($tags[$tag] as $value) {
                $padding = str_repeat(' ', $maxTagLen - strlen($tag) + 1);
                $out .= "{$indent} * @{$tag}{$padding}{$value}\n";
            }
            unset($tags[$tag]);
        }

        // Unordered remaining tags
        foreach ($tags as $tag => $values) {
            foreach ($values as $value) {
                $padding = str_repeat(' ', $maxTagLen - strlen($tag) + 1);
                $out .= "{$indent} * @{$tag}{$padding}{$value}\n";
            }
        }

        return "{$out}{$indent} */";
    }
}
