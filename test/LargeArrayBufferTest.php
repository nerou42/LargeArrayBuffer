<?php
declare(strict_types=1);

namespace LargeArrayBuffer\Tests;

use LargeArrayBuffer\LargeArrayBuffer;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

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
  
  public static function provideConfig(): \Generator {
    $serializers = [
      'PHP' => LargeArrayBuffer::SERIALIZER_PHP
    ];
    if(extension_loaded('igbinary')){
      $serializers['IGBinary'] = LargeArrayBuffer::SERIALIZER_IGBINARY;
    }
    if(extension_loaded('msgpack')){
      $serializers['MsgPack'] = LargeArrayBuffer::SERIALIZER_MSGPACK;
    }
    $compressors = [
      'none' => LargeArrayBuffer::COMPRESSION_NONE,
      'GZIP' => LargeArrayBuffer::COMPRESSION_GZIP
    ];
    if(extension_loaded('lz4')){
      $compressors['LZ4'] = LargeArrayBuffer::COMPRESSION_LZ4;
    }
    foreach($serializers as $s => $serializer){
      foreach($compressors as $c => $compressor){
        yield $s.'-'.$c => [$serializer, $compressor];
      }
    }
  }
  
  /**
   * @dataProvider provideConfig
   */
  //#[DataProvider('provideConfig')]
  public function testReadWrite(int $serializer, int $compression): void {
    $o = self::getObject();
    $buf = new LargeArrayBuffer(serializer: $serializer, compression: $compression);
    $buf->push($o);
    $buf->rewind();
    $buf->next();
    $this->assertEquals($o, $buf->current());
  }
  
  /**
   * @dataProvider provideConfig
   */
  //#[DataProvider('provideConfig')]
  public function testLoop(int $serializer, int $compression): void {
    $count = 1500;
    $buf = new LargeArrayBuffer(serializer: $serializer, compression: $compression);
    $objs = [];
    for($i=0;$i<$count;$i++){
      $o = new \stdClass();
      $o->idx = $i;
      $objs[] = $o;
      $buf->push($o);
    }
    $this->assertCount($count, $buf);
    $expIdx = 0;
    foreach($buf as $idx => $item){
      $this->assertEquals($expIdx, $idx);
      $this->assertEquals($item->idx, $idx);
      $this->assertEquals($objs[$idx], $item);
      $expIdx++;
    }
    $this->assertEquals($count, $expIdx);
  }
  
  public function testToJSON(): void {
    $o = self::getObject();
    
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
}
