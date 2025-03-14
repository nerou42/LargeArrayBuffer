<?php
/**
 * @author Andreas Wahlen
 */

declare(strict_types=1);

use LargeArrayBuffer\Benchmarks\LargeArrayBufferBench;
use LargeArrayBuffer\LargeArrayBuffer;

require_once dirname(__DIR__).'/vendor/autoload.php';

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

$metrics = [];
for($i = 0; $i < ITERATIONS; $i++){
  $bench = new LargeArrayBufferBench(ARRAY_SIZE);
  
  // normal array
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
  
  // normal buffer
  $start = microtime(true);
  $memBefore = memory_get_usage(true);
  $buf = new LargeArrayBuffer(128);
  $bench->bufferMeasurementsFill($buf);
  $metrics['fill_buffer'][] = [
    'time' => microtime(true) - $start,
    'mem' => memory_get_usage(true) - $memBefore,
    'size' => $buf->getSize()
  ];
  
  $start = microtime(true);
  $bench->bufferMeasurementsIterate($buf);
  $metrics['iterate_buffer'][] = [
    'time' => microtime(true) - $start,
    'mem' => memory_get_usage(true) - $memBefore,
    'size' => $buf->getSize()
  ];
  unset($buf);
  
  // buffer with GZIP
  $start = microtime(true);
  $memBefore = memory_get_usage(true);
  $buf = new LargeArrayBuffer(128, compression: LargeArrayBuffer::COMPRESSION_GZIP);
  $bench->bufferMeasurementsFill($buf);
  $metrics['fill_buffer_gz'][] = [
    'time' => microtime(true) - $start,
    'mem' => memory_get_usage(true) - $memBefore,
    'size' => $buf->getSize()
  ];
  
  $start = microtime(true);
  $bench->bufferMeasurementsIterate($buf);
  $metrics['iterate_buffer_gz'][] = [
    'time' => microtime(true) - $start,
    'mem' => memory_get_usage(true) - $memBefore,
    'size' => $buf->getSize()
  ];
  unset($buf);
  
  // buffer with LZ4
  if(extension_loaded('lz4')){
    $start = microtime(true);
    $memBefore = memory_get_usage(true);
    $buf = new LargeArrayBuffer(128, compression: LargeArrayBuffer::COMPRESSION_LZ4);
    $bench->bufferMeasurementsFill($buf);
    $metrics['fill_buffer_lz4'][] = [
      'time' => microtime(true) - $start,
      'mem' => memory_get_usage(true) - $memBefore,
      'size' => $buf->getSize()
    ];
    
    $start = microtime(true);
    $bench->bufferMeasurementsIterate($buf);
    $metrics['iterate_buffer_lz4'][] = [
      'time' => microtime(true) - $start,
      'mem' => memory_get_usage(true) - $memBefore,
      'size' => $buf->getSize()
    ];
    unset($buf);
  }
  
  if(extension_loaded('igbinary')){
    // normal buffer with igbinary
    $start = microtime(true);
    $memBefore = memory_get_usage(true);
    $buf = new LargeArrayBuffer(128, serializer: LargeArrayBuffer::SERIALIZER_IGBINARY);
    $bench->bufferMeasurementsFill($buf);
    $metrics['fill_buffer_ig'][] = [
      'time' => microtime(true) - $start,
      'mem' => memory_get_usage(true) - $memBefore,
      'size' => $buf->getSize()
    ];
    
    $start = microtime(true);
    $bench->bufferMeasurementsIterate($buf);
    $metrics['iterate_buffer_ig'][] = [
      'time' => microtime(true) - $start,
      'mem' => memory_get_usage(true) - $memBefore,
      'size' => $buf->getSize()
    ];
    unset($buf);
    
    // buffer with GZIP and igbinary
    $start = microtime(true);
    $memBefore = memory_get_usage(true);
    $buf = new LargeArrayBuffer(128, serializer: LargeArrayBuffer::SERIALIZER_IGBINARY, compression: LargeArrayBuffer::COMPRESSION_GZIP);
    $bench->bufferMeasurementsFill($buf);
    $metrics['fill_buffer_gz_ig'][] = [
      'time' => microtime(true) - $start,
      'mem' => memory_get_usage(true) - $memBefore,
      'size' => $buf->getSize()
    ];
    
    $start = microtime(true);
    $bench->bufferMeasurementsIterate($buf);
    $metrics['iterate_buffer_gz_ig'][] = [
      'time' => microtime(true) - $start,
      'mem' => memory_get_usage(true) - $memBefore,
      'size' => $buf->getSize()
    ];
    unset($buf);
    
    // buffer with LZ4 and igbinary
    if(extension_loaded('lz4')){
      $start = microtime(true);
      $memBefore = memory_get_usage(true);
      $buf = new LargeArrayBuffer(128, serializer: LargeArrayBuffer::SERIALIZER_IGBINARY, compression: LargeArrayBuffer::COMPRESSION_LZ4);
      $bench->bufferMeasurementsFill($buf);
      $metrics['fill_buffer_lz4_ig'][] = [
        'time' => microtime(true) - $start,
        'mem' => memory_get_usage(true) - $memBefore,
        'size' => $buf->getSize()
      ];
      
      $start = microtime(true);
      $bench->bufferMeasurementsIterate($buf);
      $metrics['iterate_buffer_lz4_ig'][] = [
        'time' => microtime(true) - $start,
        'mem' => memory_get_usage(true) - $memBefore,
        'size' => $buf->getSize()
      ];
      unset($buf);
    }
  }
  
  if(extension_loaded('msgpack')){
    // normal buffer with msgpack
    $start = microtime(true);
    $memBefore = memory_get_usage(true);
    $buf = new LargeArrayBuffer(128, serializer: LargeArrayBuffer::SERIALIZER_MSGPACK);
    $bench->bufferMeasurementsFill($buf);
    $metrics['fill_buffer_mp'][] = [
      'time' => microtime(true) - $start,
      'mem' => memory_get_usage(true) - $memBefore,
      'size' => $buf->getSize()
    ];
    
    $start = microtime(true);
    $bench->bufferMeasurementsIterate($buf);
    $metrics['iterate_buffer_mp'][] = [
      'time' => microtime(true) - $start,
      'mem' => memory_get_usage(true) - $memBefore,
      'size' => $buf->getSize()
    ];
    unset($buf);
    
    // buffer with GZIP and msgpack
    $start = microtime(true);
    $memBefore = memory_get_usage(true);
    $buf = new LargeArrayBuffer(128, serializer: LargeArrayBuffer::SERIALIZER_MSGPACK, compression: LargeArrayBuffer::COMPRESSION_GZIP);
    $bench->bufferMeasurementsFill($buf);
    $metrics['fill_buffer_gz_mp'][] = [
      'time' => microtime(true) - $start,
      'mem' => memory_get_usage(true) - $memBefore,
      'size' => $buf->getSize()
    ];
    
    $start = microtime(true);
    $bench->bufferMeasurementsIterate($buf);
    $metrics['iterate_buffer_gz_mp'][] = [
      'time' => microtime(true) - $start,
      'mem' => memory_get_usage(true) - $memBefore,
      'size' => $buf->getSize()
    ];
    unset($buf);
    
    // buffer with LZ4 and msgpack
    if(extension_loaded('lz4')){
      $start = microtime(true);
      $memBefore = memory_get_usage(true);
      $buf = new LargeArrayBuffer(128, serializer: LargeArrayBuffer::SERIALIZER_MSGPACK, compression: LargeArrayBuffer::COMPRESSION_LZ4);
      $bench->bufferMeasurementsFill($buf);
      $metrics['fill_buffer_lz4_mp'][] = [
        'time' => microtime(true) - $start,
        'mem' => memory_get_usage(true) - $memBefore,
        'size' => $buf->getSize()
      ];
      
      $start = microtime(true);
      $bench->bufferMeasurementsIterate($buf);
      $metrics['iterate_buffer_lz4_mp'][] = [
        'time' => microtime(true) - $start,
        'mem' => memory_get_usage(true) - $memBefore,
        'size' => $buf->getSize()
      ];
      unset($buf);
    }
  }
  
  unset($bench);
}

printResult('Fill array', $metrics, 'fill_array', 4);
printResult('Iterate over array', $metrics, 'iterate_array', 3);
printResult('Fill buffer', $metrics, 'fill_buffer', 4, true);
printResult('Iterate over buffer', $metrics, 'iterate_buffer', 3, true);
printResult('Fill buffer (GZIP)', $metrics, 'fill_buffer_gz', 3, true);
printResult('Iterate over buffer (GZIP)', $metrics, 'iterate_buffer_gz', 2, true);
if(extension_loaded('lz4')){
  printResult('Fill buffer (LZ4)', $metrics, 'fill_buffer_lz4', 3, true);
  printResult('Iterate over buffer (LZ4)', $metrics, 'iterate_buffer_lz4', 2, true);
}
if(extension_loaded('igbinary')){
  printResult('Fill buffer (igbinary)', $metrics, 'fill_buffer_ig', 2, true);
  printResult('Iterate over buffer (igbinary)', $metrics, 'iterate_buffer_ig', 1, true);
  printResult('Fill buffer (GZIP, igbinary)', $metrics, 'fill_buffer_gz_ig', 2, true);
  printResult('Iterate over buffer (GZIP, igbinary)', $metrics, 'iterate_buffer_gz_ig', 1, true);
  if(extension_loaded('lz4')){
    printResult('Fill buffer (LZ4, igbinary)', $metrics, 'fill_buffer_lz4_ig', 2, true);
    printResult('Iterate over buffer (LZ4, igbinary)', $metrics, 'iterate_buffer_lz4_ig', 1, true);
  }
}
if(extension_loaded('msgpack')){
  printResult('Fill buffer (msgpack)', $metrics, 'fill_buffer_mp', 2, true);
  printResult('Iterate over buffer (msgpack)', $metrics, 'iterate_buffer_mp', 1, true);
  printResult('Fill buffer (GZIP, msgpack)', $metrics, 'fill_buffer_gz_mp', 2, true);
  printResult('Iterate over buffer (GZIP, msgpack)', $metrics, 'iterate_buffer_gz_mp', 1, true);
  if(extension_loaded('lz4')){
    printResult('Fill buffer (LZ4, msgpack)', $metrics, 'fill_buffer_lz4_mp', 2, true);
    printResult('Iterate over buffer (LZ4, msgpack)', $metrics, 'iterate_buffer_lz4_mp', 1, true);
  }
}
