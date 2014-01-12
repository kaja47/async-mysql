<?php

namespace Atrox;

use React\Promise;
use React\Promise\Deferred;

class AsyncMysql {
  private $loop;
  private $makeConnection;

  /**
   * @param callable fucnction that makes connection
   * @param React\EventLoop\LoopInterface
   */
  function __construct($makeConnection, $loop) {
    $this->makeConnection = $makeConnection;
    $this->loop = $loop;
  }


  private function getConnection() {
    $conn = call_user_func($this->makeConnection);
    return ($conn === false) ? Promise\reject(new \Exception(mysqli_connect_error())) : Promise\resolve($conn);
  }


  /**
   * @param string
   * @return React\Promise\PromiseInterface
   */
  function query($query) {
    return $this->getConnection()->then(function ($conn) use ($query) {
      $status = $conn->query($query, MYSQLI_ASYNC);
      if ($status === false) {
        throw new \Exception($mysqli->error);
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
          $conn->close();
        }
      });

      return $defered->promise();
    });
  }
}
