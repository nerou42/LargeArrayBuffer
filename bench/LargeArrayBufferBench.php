<?php
declare(strict_types=1);

namespace LargeArrayBuffer\Benchmarks;

use LargeArrayBuffer\LargeArrayBuffer;
use LargeArrayBuffer\Benchmarks\Items\Measurement;

/**
 * @author Andreas Wahlen
 */
class LargeArrayBufferBench {
  
  /**
   * @readonly
   */
  private int $count;
  
  public function __construct(int $count){
    $this->count = $count;
  }
  
  private function generateMeasurement(int $index): Measurement {
    return new Measurement(
        (new \DateTimeImmutable())->sub(new \DateInterval('PT'.$index.'H')),
        $index % 500,
        random_int(-1_000_000, 1_000_000) / 1000
    );
  }
  
  public function arrayMeasurementsFill(): array {
    $arr = [];
    for($i = 0; $i < $this->count; $i++){
      $arr[] = $this->generateMeasurement($i);
    }
    return $arr;
  }
  
  public function arrayMeasurementsIterate(array $arr): void {
    foreach($arr as $index => $item){
      $index;
      $item;
    }
  }
  
  public function bufferMeasurementsFill(LargeArrayBuffer $buf): void {
    for($i = 0; $i < $this->count; $i++){
      $buf->push($this->generateMeasurement($i));
    }
  }
  
  public function bufferMeasurementsIterate(LargeArrayBuffer $buf): void {
    foreach($buf as $index => $item){
      $index;
      $item;
    }
  }
}
