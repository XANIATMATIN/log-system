<?php

namespace MatinUtils\LogSystem;

use MatinUtils\EasySocket\Client as EasySocketClient;

class SocketClient extends EasySocketClient
{
    public function send($data = [])
    {
        return $this->writeOnSocket(app('easy-socket')->prepareMessage($this->formatter($data['pid'], $data)));
    }

    protected function formatter($pid, $data)
    {
        return $pid . json_encode($data);
    }
}
