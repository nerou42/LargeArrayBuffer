<?php
declare(strict_types=1);

namespace LargeArrayBuffer;

/**
 * @template E of object|array|scalar|null
 * @extends \Iterator<int<0, max>, E>
 * @psalm-suppress TooManyTemplateParams
 * @author Andreas Wahlen
 */
interface ArrayBufferInterface extends \Iterator, \Countable {
  
  /**
   * @psalm-param E $item
   */
  public function push(mixed $item): void;
  
  /**
   * @psalm-return list<E>
   */
  public function toArray(): array;
}
