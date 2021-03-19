<?php

declare(strict_types=1);

use Rector\Core\Configuration\Option;
use Rector\Set\ValueObject\SetList;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set(
        Option::SETS,
        [
            SetList::PHP_71,
            SetList::PHP_72,
            SetList::PHP_73,
            SetList::PHP_74,
            SetList::PHP_80,
            SetList::CODE_QUALITY,
            SetList::CODE_QUALITY_STRICT,
            SetList::CODING_STYLE,
            SetList::DEAD_CODE,
            SetList::EARLY_RETURN,
            SetList::ORDER,
            SetList::NAMING,
            SetList::TYPE_DECLARATION,
            SetList::MONOLOG_20,
            SetList::SYMFONY_50,
            SetList::SYMFONY_50_TYPES,
            SetList::SYMFONY_52,
            SetList::SYMFONY_CODE_QUALITY,
            SetList::SYMFONY_AUTOWIRE,
            SetList::SYMFONY_CONSTRUCTOR_INJECTION,
            SetList::FRAMEWORK_EXTRA_BUNDLE_50,
            SetList::DOCTRINE_25,
            SetList::DOCTRINE_SERVICES,
            SetList::DOCTRINE_CODE_QUALITY,
            SetList::DOCTRINE_REPOSITORY_AS_SERVICE,
            SetList::DOCTRINE_COMMON_20,
            SetList::TWIG_240,
            SetList::TWIG_UNDERSCORE_TO_NAMESPACE,
        ]
    );
    $parameters->set(Option::SYMFONY_CONTAINER_XML_PATH_PARAMETER, __DIR__.'/var/cache/dev/App_KernelDevDebugContainer.xml');
    $parameters->set(
        Option::SKIP,
        [
            __DIR__.'/features/*',
            __DIR__.'/vendor/*',
            __DIR__.'/var/*',
            __DIR__.'/phpinsights.php',
            Rector\Php74\Rector\Assign\NullCoalescingOperatorRector::class,
            Rector\CodingStyle\Rector\ClassConst\VarConstantCommentRector::class,
            Rector\TypeDeclaration\Rector\ClassMethod\AddArrayParamDocTypeRector::class,
            Rector\TypeDeclaration\Rector\ClassMethod\AddArrayReturnDocTypeRector::class,
            Rector\Naming\Rector\ClassMethod\RenameParamToMatchTypeRector::class,
            Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector::class,
            Rector\CodeQualityStrict\Rector\If_\MoveOutMethodCallInsideIfConditionRector::class,
            Rector\Naming\Rector\ClassMethod\MakeGetterClassMethodNameStartWithGetRector::class,
            Rector\EarlyReturn\Rector\If_\ChangeAndIfToEarlyReturnRector::class,
            Rector\EarlyReturn\Rector\Return_\PreparedValueToEarlyReturnRector::class,
        ]
    );
};
