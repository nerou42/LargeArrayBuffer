<?php
declare(strict_types=1);

namespace LargeArrayBuffer;

/**
 * @author Andreas Wahlen
 * @template E of object|array|scalar|null
 * @implements \Iterator<int, E>
 * @psalm-suppress TooManyTemplateParams
 */
class LargeArrayBuffer implements \Iterator, \Countable {

  public const SERIALIZER_PHP = 1;
  //public const SERIALIZER_JSON = 2;

  public const COMPRESSION_NONE = 0;
  public const COMPRESSION_GZIP = 1;

  /**
   * @readonly
   * @var self::SERIALIZER_*
   */
  private int $serializer;

  /**
   * @readonly
   * @var self::COMPRESSION_*
   */
  private int $compression;

  /**
   * @var resource
   */
  private $stream;

  private int $count = 0;

  private int $index = 0;

  private ?string $current = null;

  /**
   * @param int $maxMemoryMiB maximum memory usage in MiB, when more data is pushed, disk space is used
   * @psalm-param self::SERIALIZER_* $serializer
   * @psalm-param self::COMPRESSION_* $compression
   * @throws \RuntimeException if php://temp could not be opened
   */
  public function __construct(int $maxMemoryMiB = 1024, int $serializer = self::SERIALIZER_PHP, int $compression = self::COMPRESSION_NONE) {
    $this->serializer = $serializer;
    $this->compression = $compression;
    $stream = fopen('php://temp/maxmemory:'.($maxMemoryMiB * 1024 * 1024), 'r+');
    if($stream === false) {
      throw new \RuntimeException('failed to open php://temp file descriptor');
    }
    $this->stream = $stream;
  }

  /**
   * @psalm-param E $item
   * @throws \RuntimeException if unable to write to php://temp
   */
  public function push(mixed $item): void {
    $serialized = match($this->serializer){
      //self::SERIALIZER_JSON => json_encode($item, JSON_THROW_ON_ERROR),
      default => serialize($item)
    };
    $compressed = match($this->compression){
      self::COMPRESSION_GZIP => gzdeflate($serialized),
      default => $serialized
    };
    $res = fwrite($this->stream, addcslashes($compressed, "\\\r\n")."\n");
    if($res === false){
      throw new \RuntimeException('could not write to php://temp');
    }
    $this->index++;
    $this->count++;
  }

  public function rewind(): void {
    fseek($this->stream, 0);
    $this->current = null;
    $this->index = 0;
  }

  /**
   * @throws \RuntimeException if unable to read from php://temp
   */
  public function next(): void {
    if(feof($this->stream)) {
      $this->current = null;
      return;
    }
    $line = fgets($this->stream);
    if($line === false) {
      throw new \RuntimeException('could not read line from php://temp');
    }
    $compressed = stripcslashes($line);
    $this->current = match($this->compression){
      self::COMPRESSION_GZIP => gzinflate($compressed),
      default => $compressed
    };
    $this->index++;
  }

  /**
   * @psalm-return E
   * @throws \RuntimeException if next() and valid() have not been called before or EOF is reached
   */
  public function current(): mixed {
    if($this->current === null) {
      throw new \RuntimeException('index out of bounds (you might want to call next() and/or valid() before!)');
    }
    return match($this->serializer){
      //self::SERIALIZER_JSON => json_decode($this->current, flags: JSON_THROW_ON_ERROR),
      default => unserialize($this->current)
    };
  }

  public function key(): int {
    return $this->index - 1;
  }

  public function valid(): bool {
    if($this->current === null) {
      $this->next();
    }
    return $this->current !== null;
  }

  /**
   * @return int|null size in bytes or null if unknown
   */
  public function getSize(): ?int {
    return fstat($this->stream)['size'] ?? null;
  }

  public function count(): int {
    return $this->count;
  }
  
  /**
   * @param string|resource $dest filename or resource to write to
   * @param int $flags see json_encode for documentation
   * @param int $depth see json_encode for documentation
   * @psalm-param int<1, 2147483647> $depth
   * @throws \RuntimeException
   */
  public function toJSONFile($dest, int $flags = JSON_THROW_ON_ERROR, int $depth = 512): void {
    if(is_string($dest)){
      $stream = fopen($dest, 'w');
      if($stream === false){
        throw new \RuntimeException('unable to open file: '.$dest);
      }
    } else {
      $stream = $dest;
    }
    fwrite($stream, '[');
    $first = true;
    foreach($this as $item){
      if(!$first){
        fwrite($stream, ',');
      }
      if(($flags & JSON_PRETTY_PRINT) > 0){
        fwrite($stream, PHP_EOL.'    ');
      }
      fwrite($stream, json_encode($item, $flags, $depth));
      fflush($stream);
      $first = false;
    }
    if(($flags & JSON_PRETTY_PRINT) > 0){
      fwrite($stream, PHP_EOL);
    }
    fwrite($stream, ']');
    if(is_string($dest)){
      fclose($stream);
    }
  }
  
  /**
   * @psalm-return list<E>
   */
  public function toArray(): array {
    $res = [];
    foreach($this as $item){
      $res[] = $item;
    }
    return $res;
  }

  public function __destruct() {
    /**
     * @psalm-suppress InvalidPropertyAssignmentValue
     */
    fclose($this->stream);
  }
}
