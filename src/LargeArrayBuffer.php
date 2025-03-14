<?php
declare(strict_types=1);

namespace LargeArrayBuffer;

/**
 * @template E of object|array|scalar|null
 * @implements ArrayBufferInterface<E>
 * @author Andreas Wahlen
 */
class LargeArrayBuffer implements ArrayBufferInterface {

  public const SERIALIZER_PHP = 1;
  public const SERIALIZER_IGBINARY = 2;
  public const SERIALIZER_MSGPACK = 3;

  public const COMPRESSION_NONE = 0;
  public const COMPRESSION_GZIP = 1;
  public const COMPRESSION_LZ4 = 2;

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

  /**
   * @var int<0, max>
   */
  private int $count = 0;

  /**
   * @var int<0, max>
   */
  private int $index = 0;

  private ?string $current = null;

  /**
   * @param int $maxMemoryMiB maximum memory usage in MiB, when more data is pushed, disk space is used
   * @psalm-param int<1,max> $maxMemoryMiB
   * @psalm-param self::SERIALIZER_* $serializer
   * @psalm-param self::COMPRESSION_* $compression
   * @throws \InvalidArgumentException if an unsupported serialization was requested
   * @throws \InvalidArgumentException if an unsupported compression was requested
   * @throws \RuntimeException if php://temp could not be opened
   */
  public function __construct(int $maxMemoryMiB = 1024, int $serializer = self::SERIALIZER_PHP, int $compression = self::COMPRESSION_NONE) {
    $this->serializer = $serializer;
    if($this->serializer === self::SERIALIZER_IGBINARY && !extension_loaded('igbinary')){
      throw new \InvalidArgumentException('igbinary serializer was requested, but ext-igbinary is not loaded');
    }
    if($this->serializer === self::SERIALIZER_MSGPACK && !extension_loaded('msgpack')){
      throw new \InvalidArgumentException('msgpack serializer was requested, but ext-msgpack is not loaded');
    }
      
    $this->compression = $compression;
    if($this->compression === self::COMPRESSION_LZ4 && !extension_loaded('lz4')){
      throw new \InvalidArgumentException('LZ4 compression was requested, but ext-lz4 is not loaded');
    }
      
    $stream = fopen('php://temp/maxmemory:'.($maxMemoryMiB * 1024 * 1024), 'r+');
    if($stream === false) {
      throw new \RuntimeException('failed to open php://temp file descriptor');
    }
    $this->stream = $stream;
  }

  /**
   * @psalm-param E $item
   * @throws \RuntimeException if unable to write to php://temp, the serialization failed or the compression failed
   */
  public function push(mixed $item): void {
    $serialized = match($this->serializer){
      self::SERIALIZER_IGBINARY => igbinary_serialize($item),
      self::SERIALIZER_MSGPACK => msgpack_serialize($item),
      default => serialize($item)
    };
    if($serialized === false){
      throw new \RuntimeException('failed to serialize data');
    }
    /** @var string|false $compressed */
    $compressed = match($this->compression){
      self::COMPRESSION_GZIP => gzdeflate($serialized),
      self::COMPRESSION_LZ4 => lz4_compress($serialized),
      default => $serialized
    };
    if($compressed === false){
      throw new \RuntimeException('failed to compress data');
    }
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
    if(feof($this->stream) || $this->count === $this->index) {   // if nothing to read, no data left
      $this->current = null;
      return;
    }
    $line = fgets($this->stream);
    if($line === false) {
      $this->current = null;
      if(feof($this->stream)){
        return;
      } else {
        throw new \RuntimeException('could not read line from php://temp at index '.$this->index.' of '.$this->count.' items');
      }
    }
    $line = substr($line, 0, strlen($line) - 1);  // cut off line break
    $compressed = stripcslashes($line);
    /** @var string|false $serialized */
    $serialized = match($this->compression){
      self::COMPRESSION_GZIP => gzinflate($compressed),
      self::COMPRESSION_LZ4 => lz4_uncompress($compressed),
      default => $compressed
    };
    if($serialized === false){
      throw new \RuntimeException('failed to uncompress data');
    }
    $this->current = $serialized;
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
    /** @psalm-var E $res */
    $res = match($this->serializer){
      self::SERIALIZER_IGBINARY => igbinary_unserialize($this->current),
      self::SERIALIZER_MSGPACK => msgpack_unserialize($this->current),
      default => unserialize($this->current)
    };
    return $res;
  }

  /**
   * {@inheritDoc}
   * @see \Iterator::key()
   * @psalm-return int<0, max>
   * @psalm-mutation-free
   */
  public function key(): int {
    return max(0, $this->index - 1);
  }

  public function valid(): bool {
    if($this->current === null) {
      $this->next();
    }
    return $this->current !== null;
  }

  /**
   * @return int|null size in bytes or null if unknown
   * @psalm-mutation-free
   */
  public function getSize(): ?int {
    return fstat($this->stream)['size'] ?? null;
  }

  /**
   * {@inheritDoc}
   * @see \Countable::count()
   * @psalm-return int<0, max>
   * @psalm-mutation-free
   */
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
      $json = json_encode($item, $flags, $depth);
      if($json === false){
        if(is_string($dest)){
          fclose($stream);
        }
        throw new \RuntimeException('failed to serialize data');
      }
      fwrite($stream, $json);
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
