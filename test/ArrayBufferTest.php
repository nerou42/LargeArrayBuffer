<?php
declare(strict_types=1);

namespace LargeArrayBuffer\Tests;

use PHPUnit\Framework\TestCase;
use LargeArrayBuffer\ArrayBuffer;

/**
 * @author Andreas Wahlen
 */
class ArrayBufferTest extends TestCase {
  
  public static function provideTestData(): array {
    return [
      [1_000, 10_000],
      [10_000, 1_000],
      [1_000, 1_000]
    ];
  }
  
  /**
   * @dataProvider provideTestData
   */
  //#[DataProvider('provideTestData')]
  public function testBuffer(int $items, int $threshold): void {
    $o = new \stdClass();
    $o->foo = 'hello world!'.PHP_EOL;
    $o->bar = new \DateTimeImmutable();
    $o->a = ['test', 123];
    $o->str = 'hello world!\\n';
    
    $buf = new ArrayBuffer($threshold);
    for($i = 0; $i < $items; $i++){
      $buf->push($o);
    }
    $this->assertCount($items, $buf);
    foreach($buf as $item){
      $this->assertEquals($o, $item);
    }
    $prop = new \ReflectionProperty($buf, 'buffer');
    $prop->setAccessible(true);
    $this->assertCount($items > $threshold ? $items : 0, $prop->getValue($buf));
  }
}
