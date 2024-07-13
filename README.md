# LargeArrayBuffer

[![License](https://poser.pugx.org/nerou/large-array-buffer/license)](https://packagist.org/packages/nerou/large-array-buffer)
[![PHP Version Require](https://poser.pugx.org/nerou/large-array-buffer/require/php)](https://packagist.org/packages/nerou/large-array-buffer)
[![Version](https://poser.pugx.org/nerou/large-array-buffer/version)](https://packagist.org/packages/nerou/large-array-buffer)
[![Psalm Type Coverage](https://shepherd.dev/github/nerou42/LargeArrayBuffer/coverage.svg)](https://packagist.org/packages/nerou/large-array-buffer)

## This is for you, if...

...you are working with pretty large arrays that conflict with close to every memory limit you can set. 
The array elements on the other hand should not be that big, such that you can keep a single element in memory quite easily.
If the array gets too big for memory, it will be moved to disk temporarily and transparently. 
You can still iterate over it as if it was a normal array.

One typical use case would be to load a lot of datasets from a database at once. (There are reasons to prefer this over running multiple queries.) See *Usage* below for an example for this use case using this library.

## Install

Note: This library requires PHP 8.0+!

Use composer to install this library:

`composer require nerou/large-array-buffer`

There are pretty much no dependencies with some exceptions:

- If you want to use the `toJSONFile()` method, you need to install `ext-json` (PHP's PECL JSON extension) as well.
- If you want to use the igbinary serializer, `ext-igbinary` is required. See [php-ext-igbinary](https://github.com/igbinary/igbinary).
- If you want to use LZ4 compression, `ext-lz4` is required. See [php-ext-lz4](https://github.com/kjdev/php-ext-lz4).

## Usage

```php
$pdo = new PDO(/* your database credentials */);
$stmt = $pdo->query('SELECT * FROM SomeDatabaseTable', PDO::FETCH_ASSOC);

$buffer = new LargeArrayBuffer();       // explicit use of LargeArrayBuffer
$buffer = new ArrayBuffer(1000);        // wrapper using `array` until given threshold (item count) is reached,
                                        // then switching to LargeArrayBuffer

while(($dataset = $stmt->fetch()) !== false){  // load one dataset at a time
    $buffer->push($dataset);    // push this dataset to the buffer
}

// ...

foreach($buffer as $item){
    // work with your data here
}
```

### Options

The constructor of `LargeArrayBuffer` provides some options:

1. You can set the threshold when to move the data to disk. When pushing data to the buffer, it is stored in memory until it gets too large.
    E.g.: `new LargeArrayBuffer(512);` to set a 512 MiB threshold. 
1. You can choose either the PHP serializer or the [igbinary](https://github.com/igbinary/igbinary) serializer (PHP serializer is default).
    E.g.: `new LargeArrayBuffer(serializer: LargeArrayBuffer::COMPRESSION_IGBINARY);`
1. You can enable GZIP or LZ4 compression for the serialized items. Although this is recommended only if your items are pretty big like > 1 KiB each. E.g.: `new LargeArrayBuffer(compression: LargeArrayBuffer::COMPRESSION_GZIP);`. Note, that LZ4 compression requires [ext-lz4](https://github.com/kjdev/php-ext-lz4) to be installed.

### Read from the buffer

There are several options to read the data:

1. Iterate: As you might have seen in the example above, you can iterate over the buffer just like over an array with `foreach`. 
    `foreach($buffer as $item){ /* work with your data here */ }`
1. `toArray()`: If you have a set of buffers which do not fit in memory all together but one at a time, you can use `$buffer->toArray()` to get an array to work with.
1. `toJSONFile()`: If you want to write the array in JSON format to some file or `resource`, use this method. It supports all the `json_encode()` options and flags.

### Buffer stats

There are some stats you can obtain:

1. `count()`: The number of items in the buffer.
1. `getSize()`: The number of bytes of the serialized (!) data.

## How it works

To put it in one sentence: This library uses [php://temp](https://www.php.net/manual/en/wrappers.php.php) as well as PHP's [serialize](https://www.php.net/manual/en/function.serialize.php)/[unserialize](https://www.php.net/manual/en/function.unserialize.php) functions to store an array on disk if it gets too large. 

## Limitations and concerns

- associative arrays are not supported
- the item type needs to be compatible with PHP's [serialize](https://www.php.net/manual/en/function.serialize.php)/[unserialize](https://www.php.net/manual/en/function.unserialize.php) functions
- since storage drives (even PCIe SSDs) are a lot slower than memory and de-/serialization needs to be done, you trade hard memory overflows for performance losses

### Benchmark

A benchmark with 1 million measurements (consisting of DateTimeImmutable, int and float) using PHP 8.1 with 10 iterations comparing a normal array with the LargeArrayBuffer gave the following results (LargeArrayBuffer was configured with a memory limit of 128 MiB):

| Action | Serializer | Compression | Consumed time | Consumed memory | Buffer size |
| :--- | :--- | :--- | ---: | ---: | ---: |
| Fill array | none | none | 1.57 s | 490.0 MiB | NA |
| Iterate over array | none | none | 0.27 s | 492.0 MiB | NA |
| Fill buffer | PHP | none | 4.27 s | 0 B | 378.7 MiB |
| Iterate over buffer | PHP | none | 2.85 s | 0 B | 378.7 MiB |
| Fill buffer | PHP | GZIP | 24.76 s | 0 B | 192.5 MiB |
| Iterate over buffer | PHP | GZIP | 6.79 s | 0 B | 192.5 MiB |
| Fill buffer | PHP | LZ4 | 4.89 s | 0 B | 241.0 MiB |
| Iterate over buffer | PHP | LZ4 | 2.93 s | 0 B | 241.0 MiB |
| Fill buffer | igbinary | none | 4.26 s | 0 B | 319.1 MiB |
| Iterate over buffer | igbinary | none | 3.41 s | 0 B | 319.1 MiB |
| Fill buffer | igbinary | GZIP | 21.50 s | 0 B | 173.2 MiB |
| Iterate over buffer | igbinary | GZIP | 4.80 s | 0 B | 173.2 MiB |
| Fill buffer | igbinary | LZ4 | 4.38 s | 0 B | 195.1 MiB |
| Iterate over buffer | igbinary | LZ4 | 3.17 s | 0 B | 195.1 MiB |

Note: 

- The peak memory usage using the buffer is about its memory limit. The table shows the memory usage after the specified action.
- PHP seems to cache the array once it is created for the first time, although `unset` is used. That is why I have not put the average value in the table for this specific value but the maximum (first run).
- The serialized data is smaller than the binary data in memory. I have absolutly no idea why.

To reproduce call bench/benchmark.php. 

## License

This library is licensed under the MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
