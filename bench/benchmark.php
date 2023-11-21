<?php
/**
 * @author Andreas Wahlen
 */

declare(strict_types=1);

use LargeArrayBuffer\Benchmarks\LargeArrayBufferBench;
use LargeArrayBuffer\Benchmarks\Items\Measurement;
use LargeArrayBuffer\LargeArrayBuffer;

define('ITERATIONS', 10);
define('ARRAY_SIZE', 1_000_000);

function formatBytes(float $bytes): string {
  $scales = ['B', 'kiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'];
  $exp = intval(floor(log($bytes, 1024)));
  return round($bytes / pow(1024, $exp), 1).' '.$scales[$exp];
}

function getAverage(array $metrics, string $key): float {
  return array_sum(array_column($metrics, $key)) / ITERATIONS;
}

function getMax(array $metrics, string $key): float {
  return max(array_column($metrics, $key));
}

function printResult(string $label, array $metrics, string $key, int $tabs = 1, bool $inclSize = false): void {
  echo $label.' (avg/max):'.str_repeat("\t", $tabs).
      number_format(getAverage($metrics[$key], 'time'), 2).' s/'.number_format(getMax($metrics[$key], 'time'), 2).' s | '.
      formatBytes(getAverage($metrics[$key], 'mem')).'/'.formatBytes(getMax($metrics[$key], 'mem')).' | '.
      ($inclSize ? formatBytes(getAverage($metrics[$key], 'size')).'/'.formatBytes(getMax($metrics[$key], 'size')) : '').
      PHP_EOL;
}

require_once dirname(__DIR__).'/vendor/autoload.php';

$metrics = [];
for($i = 0; $i < ITERATIONS; $i++){
  $bench = new LargeArrayBufferBench(ARRAY_SIZE);
  
  $start = microtime(true);
  $memBefore = memory_get_usage(true);
  $arr = $bench->arrayMeasurementsFill();
  $metrics['fill_array'][] = [
    'time' => microtime(true) - $start,
    'mem' => memory_get_usage(true) - $memBefore
  ];
  
  $start = microtime(true);
  $bench->arrayMeasurementsIterate($arr);
  $metrics['iterate_array'][] = [
    'time' => microtime(true) - $start,
    'mem' => memory_get_usage(true) - $memBefore
  ];
  unset($arr);
  
  $start = microtime(true);
  $memBefore = memory_get_usage(true);
  $buf = new LargeArrayBuffer(256);
  $bench->bufferMeasurementsFill($buf);
  $time = microtime(true) - $start;
  $mem = memory_get_usage(true) - $memBefore;
  $metrics['fill_buffer'][] = [
    'time' => microtime(true) - $start,
    'mem' => memory_get_usage(true) - $memBefore,
    'size' => $buf->getSize()
  ];
  
  $start = microtime(true);
  $bench->bufferMeasurementsIterate($buf);
  $time = microtime(true) - $start;
  $mem = memory_get_usage(true) - $memBefore;
  $metrics['iterate_buffer'][] = [
    'time' => microtime(true) - $start,
    'mem' => memory_get_usage(true) - $memBefore,
    'size' => $buf->getSize()
  ];
  unset($buf);
  
  unset($bench);
}

printResult('Fill array', $metrics, 'fill_array', 2);
printResult('Iterate over array', $metrics, 'iterate_array', 1);
printResult('Fill buffer', $metrics, 'fill_buffer', 2, true);
printResult('Iterate over buffer', $metrics, 'iterate_buffer', 1, true);
