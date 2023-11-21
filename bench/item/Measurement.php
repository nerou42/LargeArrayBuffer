<?php
declare(strict_types=1);

namespace LargeArrayBuffer\Benchmarks\Items;

/**
 * @author Andreas Wahlen
 */
class Measurement {

  private \DateTimeImmutable $timestamp;
  private int $sensorID;
  private float $value;
  
  public function __construct(\DateTimeImmutable $timestamp, int $sensorID, float $value) {
    $this->timestamp = $timestamp;
    $this->sensorID = $sensorID;
    $this->value = $value;
  }
}
