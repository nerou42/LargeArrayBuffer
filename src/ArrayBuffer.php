<?php
declare(strict_types=1);

namespace LargeArrayBuffer;

/**
 * @template E of object|array|scalar|null
 * @implements ArrayBufferInterface<E>
 * @api
 * @author Andreas Wahlen
 */
class ArrayBuffer implements ArrayBufferInterface {

  /**
   * @readonly
   * @var int<1, max>
   */
  private int $itemThreshold;
  /**
   * @readonly
   * @var LargeArrayBuffer<E>
   */
  private LargeArrayBuffer $buffer;
  /**
   * @var list<E>
   */
  private array $array = [];
  
  /**
   * @param int<1, max> $itemThreshold
   * @param int $maxMemoryMiB maximum memory usage in MiB, when more data is pushed, disk space is used
   * @psalm-param int<1,max> $maxMemoryMiB
   * @psalm-param LargeArrayBuffer::SERIALIZER_* $serializer
   * @psalm-param LargeArrayBuffer::COMPRESSION_* $compression
   */
  public function __construct(int $itemThreshold, int $maxMemoryMiB = 1024, int $serializer = LargeArrayBuffer::SERIALIZER_PHP, int $compression = LargeArrayBuffer::COMPRESSION_NONE) {
    $this->itemThreshold = $itemThreshold;
    /** @var LargeArrayBuffer<E> $buffer */
    $buffer = new LargeArrayBuffer($maxMemoryMiB, $serializer, $compression);
    $this->buffer = $buffer;
  }

  public function next(): void {
    if($this->buffer->count() > 0){
      $this->buffer->next();
    } else {
      next($this->array);
    }
  }

  public function valid(): bool {
    if($this->buffer->count() > 0){
      return $this->buffer->valid();
    } else {
      return key($this->array) !== null;
    }
  }

  public function current(): mixed {
    if($this->buffer->count() > 0){
      return $this->buffer->current();
    } else {
      return current($this->array);
    }
  }

  public function rewind(): void {
    if($this->buffer->count() > 0){
      $this->buffer->rewind();
    } else {
      reset($this->array);
    }
  }

  public function count(): int {
    if($this->buffer->count() > 0){
      return $this->buffer->count();
    } else {
      return count($this->array);
    }
  }

  public function key(): int {
    if($this->buffer->count() > 0){
      return $this->buffer->key();
    } else {
      /** @var int $res */
      $res = key($this->array);
      return $res;
    }
  }
  
  public function push(mixed $item): void {
    // switch to buffer if threshold is reached
    if(count($this->array) >= $this->itemThreshold){
      foreach($this->array as $tmpItem){
        $this->buffer->push($tmpItem);
      }
      $this->array = [];    // save some memory
    }
    // add new item
    if($this->buffer->count() > 0){
      $this->buffer->push($item);
    } else {
      $this->array[] = $item;
    }
  }
  
  public function toArray(): array {
    if($this->buffer->count() > 0){
      return $this->buffer->toArray();
    } else {
      return $this->array;
    }
  }
  
  /**
   * @psalm-return \SplFixedArray<E>
   */
  public function toFixedArray(): \SplFixedArray {
    if($this->buffer->count() > 0){
      return $this->buffer->toFixedArray();
    } else {
      $res = new \SplFixedArray(count($this->array));
      foreach($this->array as $idx => $item){
        $res[$idx] = $item;
      }
      return $res;
    }
  }
  
  /**
   * @return \Generator send something other than null to terminate
   * @psalm-return \Generator<int, E, mixed, void>
   */
  public function toGenerator(): \Generator {
    if($this->buffer->count() > 0){
      yield from $this->buffer->toGenerator();
    } else {
      foreach($this->array as $item){
        $cmd = yield $item;
        if($cmd !== null){
          break;
        }
      }
    }
  }
}
