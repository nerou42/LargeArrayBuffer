<?php
declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;

return static function (RectorConfig $rectorConfig): void {
  $rectorConfig->paths([
    __DIR__.'/src',
    __DIR__.'/test'
  ]);
  
  $rectorConfig->phpVersion(PhpVersion::PHP_85);

  $rectorConfig->sets([
    LevelSetList::UP_TO_PHP_80
  ]);
  
  $rectorConfig->skip([
    ClassPropertyAssignToConstructorPromotionRector::class
  ]);
};
