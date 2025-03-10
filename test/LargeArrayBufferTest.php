<?php
declare(strict_types=1);

namespace LargeArrayBuffer\Tests;

use LargeArrayBuffer\LargeArrayBuffer;
use PHPUnit\Framework\TestCase;

/**
 * @author Andreas Wahlen
 */
class LargeArrayBufferTest extends TestCase {
  
  private static function getObject(): object {
    $o = new \stdClass();
    $o->foo = 'hello world!'.PHP_EOL;
    $o->bar = new \DateTimeImmutable();
    $o->a = ['test', 123];
    $o->str = 'hello world!\\n';
    return $o;
  }
  
  public function testEmpty(): void {
    $buf = new LargeArrayBuffer();
    $runs = 0;
    foreach($buf as $item){
      $runs++;
      $item;
    }
    $this->assertEquals(0, $buf->count());
    $this->assertEquals(0, $runs);
  }
  
  public static function provideObject(): array {
    $o = self::getObject();
    return [
      [$o, LargeArrayBuffer::SERIALIZER_PHP, LargeArrayBuffer::COMPRESSION_NONE],
      [$o, LargeArrayBuffer::SERIALIZER_PHP, LargeArrayBuffer::COMPRESSION_GZIP],
    ];
  }
  
  /**
   * @dataProvider provideObject
   */
  public function testReadWrite(object $o, int $serializer, int $compression): void {
    $buf = new LargeArrayBuffer(serializer: $serializer, compression: $compression);
    $buf->push($o);
    $buf->rewind();
    $buf->next();
    $this->assertEquals($o, $buf->current());
  }
  
  /**
   * @requires extension igbinary
   */
  public function testReadWriteIgbinary(): void {
    $o = self::getObject();
    $buf = new LargeArrayBuffer(serializer: LargeArrayBuffer::SERIALIZER_IGBINARY);
    $buf->push($o);
    $buf->rewind();
    $buf->next();
    $this->assertEquals($o, $buf->current());
  }
  
  /**
   * @requires extension lz4
   */
  public function testReadWriteLZ4(): void {
    $o = self::getObject();
    $buf = new LargeArrayBuffer(compression: LargeArrayBuffer::COMPRESSION_LZ4);
    $buf->push($o);
    $buf->rewind();
    $buf->next();
    $this->assertEquals($o, $buf->current());
  }
  
  public function testLoop(): void {
    $count = 15;
    $buf = new LargeArrayBuffer();
    $objs = [];
    for($i=0;$i<$count;$i++){
      $o = new \stdClass();
      $o->idx = $i;
      $objs[] = $o;
      $buf->push($o);
    }
    $this->assertCount($count, $buf);
    $runs = 0;
    foreach($buf as $idx => $item){
      $runs++;
      $this->assertGreaterThanOrEqual(0, $idx);
      $this->assertLessThan($count, $idx);
      $this->assertEquals($objs[$idx], $item);
    }
    $this->assertEquals($count, $runs);
  }
  
  public function testToJSON(): void {
    $o = $this->getObject();
    
    $buf = new LargeArrayBuffer();
    $buf->push($o);
    $buf->push($o);
    $stream = fopen('php://memory', 'r+');
    $buf->toJSONFile($stream);
    fseek($stream, 0); // rewind
    $json = stream_get_contents($stream);
    fclose($stream);
    $this->assertEquals(json_encode([$o, $o], JSON_THROW_ON_ERROR), $json);
  }
  
  public function provideItems(): array {
    return [
      [['hello world!', 'just another string']]
    ];
  }
  
  /**
   * @dataProvider provideItems
   */
  public function testToArray(array $items): void {
    $buf = new LargeArrayBuffer();
    foreach($items as $item){
      $buf->push($item);
    }
    $this->assertSame($items, $buf->toArray());
  }
  
  /**
   * @dataProvider provideItems
   */
  public function testToFixedArray(array $items): void {
    $buf = new LargeArrayBuffer();
    foreach($items as $item){
      $buf->push($item);
    }
    $this->assertSame($items, $buf->toFixedArray()->toArray());
  }
  
  /**
   * @dataProvider provideItems
   */
  public function testToGenerator(array $items): void {
    $buf = new LargeArrayBuffer();
    foreach($items as $item){
      $buf->push($item);
    }
    $this->assertSame($items, iterator_to_array($buf->toGenerator()));
  }
}
