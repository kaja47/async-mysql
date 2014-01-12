<?php

namespace Atrox;

use React\Promise;
use React\Promise\Deferred;
use React\EventLoop\LoopInterface;


class AsyncMysql {

  private $loop;
  private $pool;


  /**
   * @param callable fucnction that makes connection
   * @param React\EventLoop\LoopInterface
   * @param Atrox\ConnectionPool
   */
  function __construct($makeConnection, LoopInterface $loop, ConnectionPool $connectionPool = null) {
    $this->loop = $loop;
    $this->pool = ($connectionPool === null) ? new ConnectionPool($makeConnection, 100) : $connectionPool;
  }


  /**
   * @param string
   * @return React\Promise\PromiseInterface
   */
  function query($query) {
    return $this->pool->getConnection()->then(function ($conn) use ($query) {
      $status = $conn->query($query, MYSQLI_ASYNC);
      if ($status === false) {
        $this->pool->freeConnection($conn);
        throw new \Exception($conn->error);
      }

      $defered = new Deferred();

      $this->loop->addPeriodicTimer(0.001, function ($timer) use ($conn, $defered) {
        $links = $errors = $reject = array($conn);
        mysqli_poll($links, $errors, $reject, 0); // don't wait, just check
        if (($read = in_array($conn, $links, true)) || ($err = in_array($conn, $errors, true)) || ($rej = in_array($conn, $reject, true))) {
          if ($read) {
            $result = $conn->reap_async_query();
            if ($result === false) {
              $defered->reject(new \Exception($conn->error));
            } else {
              $defered->resolve($result);
            }
          } elseif ($err) {
            $defered->reject($conn->error);
          } else {
            $defered->reject(new \Exception('Query was rejected'));
          }
          $timer->cancel();
          $this->pool->freeConnection($conn);
        }
      });

      return $defered->promise();
    });
  }
}



class ConnectionPool {

  private $makeConnection;
  private $maxConnections;

  /** pool of all connections (both idle and busy) */
  private $pool;

  /** pool of idle connections */
  private $idle;

  /** array of Deferred objects waiting to be resolved with connection */
  private $waiting = [];


  function __construct($makeConnection, $maxConnections = 100) {
    $this->makeConnection = $makeConnection;
    $this->maxConnections = $maxConnections;
    $this->pool = new \SplObjectStorage();
    $this->idle = new \SplObjectStorage();
  }


  function getConnection() {
    // reuse idle connections
    if (count($this->idle) > 0) {
      $this->idle->rewind();
      $conn = $this->idle->current();
      $this->idle->detach($conn);
      return Promise\resolve($conn);
    }

    // max connections reached, must wait till one connection is freed
    if (count($this->pool) >= $this->maxConnections) {
      $deferred = new Deferred();
      $this->waiting[] = $deferred;
      return $deferred->promise();
    }

    $conn = call_user_func($this->makeConnection);
    $this->pool->attach($conn);
    return ($conn === false) ? Promise\reject(new \Exception(mysqli_connect_error())) : Promise\resolve($conn);
  }


  function freeConnection(\mysqli $conn) {
    if (!empty($this->waiting)) {
      $deferred = array_shift($this->waiting);
      $deferred->resolve($conn);
    } else {
      return $this->idle->attach($conn);
    }
  }
}
