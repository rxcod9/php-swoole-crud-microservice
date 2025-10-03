<?php

declare(strict_types=1);

namespace RectorRules;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\Namespace_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;

/**
 * Adds or merges class-level PHPDoc.
 * Sets correct @category and @package based on namespace.
 */
final class AddClassDocRector extends AbstractRector
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
 * Handles all user operations
 *
 * @category  Services
 * @package   App\Services
 * @var ?int $size
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

    public function getNodeTypes(): array
    {
        return [Class_::class, Interface_::class, Trait_::class];
    }

    public function refactor(Node $node): ?Node
    {
        $filePath     = $this->file->getFilePath();
        $license            = $this->composerMeta['license'] ?? '';
        $version            = $this->composerMeta['version'] ?? '1.0.0';
        $authors            = $this->composerMeta['authors'] ?? [];
        $authorStr          = $this->getAuthorsString($authors);
        $generated          = date('Y-m-d');

        // Dynamically compute namespace from file content
        $namespace = $this->resolveNamespaceFromFile($filePath);
        $category  = $this->computeCategoryFromNamespace($namespace);


        $className = $node->name?->toString() ?? 'AnonymousClass';
        $description = $this->generateDescriptionFromClassName($className);

        $classDoc = <<<PHPDOC
/**
 * Class {$className}
 *
 * {$description}
 *
 * @category  {$category}
 * @package   {$namespace}
 * @author    {$authorStr}
 * @license   {$license}
 * @version   {$version}
 * @since     {$generated}
 */
PHPDOC;

        $existingDoc = $node->getDocComment();
        $mergedDoc   = $this->mergeDocComment($existingDoc, $classDoc);

        $node->setDocComment($mergedDoc);

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
        // Use second segment of namespace if exists, otherwise fallback to "General"
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

    private function generateDescriptionFromClassName(string $className): string
    {
        $className = preg_replace('/(Service|Manager|Repository|Handler|Processor)$/', '', $className);
        $words = preg_split('/(?=[A-Z])/', $className, -1, PREG_SPLIT_NO_EMPTY);
        $entity = $words ? strtolower(implode(' ', $words)) : strtolower($className);
        return 'Handles all ' . $entity . ' operations.';
    }

    private function resolveNamespace(Node\Stmt\Class_|Node\Stmt\Interface_|Node\Stmt\Trait_ $class): string
    {
        $namespace = $class->getAttribute('parent');
        while ($namespace && !$namespace instanceof Namespace_) {
            $namespace = $namespace->getAttribute('parent');
        }
        return $namespace instanceof Namespace_ ? $namespace->name?->toString() ?? '' : '';
    }

    private function mergeDocComment(?Doc $existing, string $newDoc): Doc
    {
        if ($existing === null) {
            return new Doc($newDoc);
        }

        $existingLines = preg_split('/\R/', trim($existing->getText(), "/* \n\t"));
        $newLines      = preg_split('/\R/', trim($newDoc, "/* \n\t"));

        $existingDesc = [];
        $existingTags = [];
        foreach ($existingLines as $line) {
            $line = trim(ltrim($line, "* \t"));
            if ($line === '') continue;
            if (str_starts_with($line, '@')) {
                [$tag, $value] = array_pad(preg_split('/\s+/', $line, 2), 2, '');
                $existingTags[$tag][] = $value;
            } else {
                $existingDesc[] = $line;
            }
        }

        $newDesc = [];
        $newTags = [];
        foreach ($newLines as $line) {
            $line = trim(ltrim($line, "* \t"));
            if ($line === '') continue;
            if (str_starts_with($line, '@')) {
                [$tag, $value] = array_pad(preg_split('/\s+/', $line, 2), 2, '');
                $newTags[$tag] = $value;
            } else {
                $newDesc[] = $line;
            }
        }

        $finalDesc = $existingDesc ?: $newDesc;
        $finalTags = $newTags + $existingTags;
        $maxLen    = max(array_map('strlen', array_keys($finalTags)));

        $merged = "/**\n";
        foreach ($finalDesc as $line) $merged .= " * {$line}\n";
        if ($finalDesc && $finalTags) $merged .= " *\n";
        foreach ($finalTags as $tag => $values) {
            if (is_array($values)) {
                foreach ($values as $value) {
                    $padding = str_repeat(' ', $maxLen - strlen($tag) + 1);
                    $merged .= " * {$tag}{$padding}{$value}\n";
                }
            } else {
                $padding = str_repeat(' ', $maxLen - strlen($tag) + 1);
                $merged .= " * {$tag}{$padding}{$values}\n";
            }
        }
        $merged .= " */";

        return new Doc($merged);
    }
}
