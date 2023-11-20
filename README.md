# LargeArrayBuffer

[![License](http://poser.pugx.org/nerou/large-array-buffer/license)](https://packagist.org/packages/nerou/large-array-buffer)
[![Version](http://poser.pugx.org/nerou/large-array-buffer/version)](https://packagist.org/packages/nerou/large-array-buffer)

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

There are pretty much no dependencies with a single exception:
If you want to use the `toJSONFile()` method, you need to install `ext-json` (PHP's PECL JSON extension) as well.

## Usage

```php
$pdo = new PDO(/* your database credentials */);
$stmt = $pdo->query('SELECT * FROM SomeDatabaseTable', PDO::FETCH_ASSOC);
$buffer = new LargeArrayBuffer();
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
1. You can enable GZIP compression for the serialized items. Although this is recommended only if your items are pretty big like > 1 KiB each. `new LargeArrayBuffer(compression: LargeArrayBuffer::COMPRESSION_GZIP);`

### Read your data from the buffer

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

## License

This library is licensed under the MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
