# AsyncMysql

Asynchronous & non-blocking MySQL driver for [React.PHP](https://github.com/reactphp/react).

## Install

Add this crap to your composer.json:

```
{
  "require": {
    "react/react": "0.2.*",
    "atrox/async-mysql": "dev-master"
  },
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/kaja47/async-mysql"
    }
  ]
}

```

## Usage

Create instance of AsyncMysql and call method `query`.
It returns [Promise](https://github.com/reactphp/promise) of [mysqli_result](http://cz2.php.net/manual/en/class.mysqli-result.php) that will be resolved imediately after query completes.

```php
<?php

$loop = React\EventLoop\Factory::create();

$makeConnection = function () { return mysqli_connect('localhost', 'user', 'pass', ' dbname'); };
$mysql = \Atrox\AsyncMysql($makeConnection, $loop);
$mysql->query('select * from ponies_and_unicorns')->then(
  function ($result) { writeHttpResponse(json_encode($result->fetch_all(MYSQLI_ASSOC))); $result->close(); },
  function ($error)  { writeHeader500(); }
);
```
