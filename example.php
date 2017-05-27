<?php
include 'vendor/autoload.php';

$pipeline = new \Pipeline\Simple();

$pipeline->map(function () {
    foreach (range(1, 3) as $i) {
        yield $i;
    }
});

$pipeline->map(function ($i) {
    yield pow($i, 2);
    yield pow($i, 3);
});

$pipeline->map(function ($i) {
    yield $i - 1;
});

$pipeline->map(function ($i) {
    yield $i * 2;
    yield $i * 4;
});

$pipeline->map(function ($i) {
    if ($i > 50) {
        yield $i;
    }
});

$pipeline->filter(function ($i) {
    return $i > 100;
});

$value = $pipeline->reduce(function ($a, $b) {
    return $a + $b;
}, 0);

// int(104)
var_dump($value);

