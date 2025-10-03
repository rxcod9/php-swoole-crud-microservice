<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;

// Core PHP 8.4+ improvements
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\DeclareStrictTypesRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;

// Type inference and PHPDoc automation
use Rector\TypeDeclaration\Rector\ClassMethod\ParamTypeByParentCallTypeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ParamTypeByMethodCallTypeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictParamRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictTypedCallRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddReturnDocblockForScalarArrayFromAssignsRector;
use RectorRules\AddFileDocBeforeDeclareRector;
use RectorRules\AddClassDocRector;

return static function (RectorConfig $rectorConfig): void {

    // 1️⃣ Autoload and scan paths
    $rectorConfig->autoloadPaths([__DIR__ . '/tests/bootstrap.php']);
    $rectorConfig->paths([__DIR__ . '/src', __DIR__ . '/tests']);

    // 2️⃣ Skip unnecessary folders
    $rectorConfig->skip([__DIR__ . '/vendor', __DIR__ . '/build', __DIR__ . '/node_modules']);

    // 3️⃣ Sets
    $rectorConfig->sets([
        SetList::CODING_STYLE,
        SetList::PHP_84,
        SetList::TYPE_DECLARATION,
        SetList::STRICT_BOOLEANS,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::NAMING,
        SetList::PRIVATIZATION,
        SetList::EARLY_RETURN,
        SetList::INSTANCEOF,
        SetList::CARBON,
    ]);

    // 4️⃣ Core rules
    $rectorConfig->rule(DeclareStrictTypesRector::class);
    $rectorConfig->rule(ClassPropertyAssignToConstructorPromotionRector::class);
    $rectorConfig->rule(ReadOnlyPropertyRector::class);

    // 5️⃣ Type inference / PHPDoc enhancement
    $rectorConfig->rule(ParamTypeByParentCallTypeRector::class);
    $rectorConfig->rule(ParamTypeByMethodCallTypeRector::class);
    $rectorConfig->rule(ReturnTypeFromStrictParamRector::class);
    $rectorConfig->rule(ReturnTypeFromStrictTypedCallRector::class);
    $rectorConfig->rule(AddReturnDocblockForScalarArrayFromAssignsRector::class);

    // 6️⃣ Optional: Add file-level doc headers for missing PHPDocs
    $rectorConfig->rule(AddFileDocBeforeDeclareRector::class);
    $rectorConfig->rule(AddClassDocRector::class);

    // 7️⃣ Cache & extensions
    $rectorConfig->cacheDirectory(__DIR__ . '/.rector_cache');
    $rectorConfig->fileExtensions(['php']);
};
