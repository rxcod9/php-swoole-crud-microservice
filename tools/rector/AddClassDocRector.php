<?php

declare(strict_types=1);

namespace RectorRules;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;

/**
 * Rector rule to add or merge class-level PHPDoc.
 * Ensures consistent @category and @package tags based on namespace.
 *
 * @category  RectorRules
 * @package   RectorRules
 * @license   MIT
 */
final class AddClassDocRector extends AbstractRector
{
    /**
     * Cached composer.json metadata.
     *
     * @var array<string, mixed>
     */
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

    /**
     * @inheritDoc
     */
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Add or merge class-level PHPDoc with correct @category and @package, preserving existing annotations',
            [
                new CodeSample(
                    <<<'CODE'
class UserService
{
    /** @var ?int $size */
    private $size;
}
CODE,
                    <<<'CODE'
/**
 * Class UserService
 *
 * Handles all user operations.
 *
 * @category  Services
 * @package   App\Services
 * @author    Example <user@example.com>
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-28
 */
class UserService
{
    /** @var ?int $size */
    private $size;
}
CODE
                )
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public function getNodeTypes(): array
    {
        return [Class_::class, Interface_::class, Trait_::class];
    }

    /**
     * Adds or updates the class-level PHPDoc with metadata and composer info.
     *
     * @param Node&Class_|Interface_|Trait_ $node
     * @return Node|null
     */
    public function refactor(Node $node): ?Node
    {
        $filePath   = $this->file->getFilePath();
        $license    = $this->composerMeta['license'] ?? '';
        $version    = $this->composerMeta['version'] ?? '1.0.0';
        $authors    = $this->composerMeta['authors'] ?? [];
        $authorStr  = $this->getAuthorsString($authors);
        $copyright  = 'Copyright (c) ' . date('Y');
        $generated  = date('Y-m-d');

        $namespace  = $this->resolveNamespaceFromFile($filePath);
        $category   = $this->computeCategoryFromNamespace($namespace);
        $className  = $node->name?->toString() ?? 'AnonymousClass';
        $desc       = $this->generateDescriptionFromClassName($className);

        $newDoc = <<<PHPDOC
/**
 * Class {$className}
 *
 * {$desc}
 *
 * @category  {$category}
 * @package   {$namespace}
 * @author    {$authorStr}
 * @copyright {$copyright}
 * @license   {$license}
 * @version   {$version}
 * @since     {$generated}
 */
PHPDOC;

        $mergedDoc = $this->mergeDocComment($node->getDocComment(), $newDoc);
        $node->setDocComment($mergedDoc);

        return $node;
    }

    /**
     * Merge an existing DocBlock with a generated one.
     *
     * @param Doc|null $existing
     * @param string   $newDoc
     * @return Doc
     */
    private function mergeDocComment(?Doc $existing, string $newDoc): Doc
    {
        $indent       = $this->extractIndent($existing);
        [$existingDesc, $existingTags] = $this->parseDoc($existing?->getText() ?? '');
        [$newDesc, $newTags]           = $this->parseDoc($newDoc);

        [$finalDesc, $finalTags] = $this->mergeDocParts($existingDesc, $existingTags, $newDesc, $newTags);

        $merged = $this->buildDocBlock($finalDesc, $finalTags, $indent);
        return new Doc($merged);
    }

    /**
     * Extracts indentation from existing doc comment.
     *
     * @param Doc|null $doc
     * @return string
     */
    private function extractIndent(?Doc $doc): string
    {
        if ($doc && preg_match('/^(\s*)\/\*\*/', $doc->getText(), $matches)) {
            return $matches[1];
        }
        return '    '; // Default indent (4 spaces)
    }

    /**
     * Parse a DocBlock string into description and tags.
     *
     * @param string $doc
     * @return array{array<int, string>, array<string, array<int, string>>}
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
                if (preg_match('/^@+(\S+)\s*(.*)$/', $line, $m)) {
                    $tag = $m[1];
                    $val = $m[2] ?? '';
                    $tags[$tag][] = $val;
                }
            } else {
                $desc[] = $line;
            }
        }

        return [$desc, $tags];
    }

    /**
     * Merge description and tags from both existing and new docs.
     *
     * @param array<int, string> $existingDesc
     * @param array<string, array<int, string>> $existingTags
     * @param array<int, string> $newDesc
     * @param array<string, array<int, string>> $newTags
     * @return array{array<int, string>, array<string, array<int, string>>}
     */
    private function mergeDocParts(
        array $existingDesc,
        array $existingTags,
        array $newDesc,
        array $newTags
    ): array {
        $finalDesc = $existingDesc ?: $newDesc;
        $finalTags = $existingTags;

        foreach ($newTags as $tag => $values) {
            if (!isset($finalTags[$tag])) {
                $finalTags[$tag] = $values;
            }
        }

        return [$finalDesc, $finalTags];
    }

    /**
     * Build a formatted PHPDoc block.
     *
     * @param array<int, string> $desc
     * @param array<string, array<int, string>> $tags
     * @param string $indent
     * @return string
     */
    private function buildDocBlock(array $desc, array $tags, string $indent): string
    {
        $tagOrder = [
            'category', 'package', 'author', 'copyright',
            'license', 'version', 'since', 'link', 'var', 'method'
        ];

        $allTags   = array_merge(array_keys($tags), $tagOrder);
        $maxTagLen = max(array_map('strlen', $allTags));
        $doc       = "{$indent}/**\n";

        foreach ($desc as $line) {
            $doc .= "{$indent} * {$line}\n";
        }

        if ($desc && $tags) {
            $doc .= "{$indent} *\n";
        }

        foreach ($tagOrder as $tag) {
            if (!isset($tags[$tag])) {
                continue;
            }
            foreach ($tags[$tag] as $val) {
                $pad = str_repeat(' ', $maxTagLen - strlen($tag) + 1);
                $doc .= "{$indent} * @{$tag}{$pad}{$val}\n";
            }
            unset($tags[$tag]);
        }

        // Append any unlisted tags
        foreach ($tags as $tag => $vals) {
            foreach ($vals as $val) {
                $pad = str_repeat(' ', $maxTagLen - strlen($tag) + 1);
                $doc .= "{$indent} * @{$tag}{$pad}{$val}\n";
            }
        }

        $doc .= "{$indent} */";
        return $doc;
    }

    // ------------------------------------------------------------------------
    // Existing helper methods (unchanged)
    // ------------------------------------------------------------------------

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
        return preg_match('/^namespace\s+([^\s;]+);/m', $content, $m) ? $m[1] : 'Global';
    }

    private function generateDescriptionFromClassName(string $className): string
    {
        $className = preg_replace('/(Service|Manager|Repository|Handler|Processor)$/', '', $className);
        $words = preg_split('/(?=[A-Z])/', $className, -1, PREG_SPLIT_NO_EMPTY);
        $entity = $words ? strtolower(implode(' ', $words)) : strtolower($className);
        return "Handles all {$entity} operations.";
    }
}
