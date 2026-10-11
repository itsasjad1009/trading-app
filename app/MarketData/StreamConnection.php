<?php

namespace App\MarketData;

use WebSocket\Client;
use WebSocket\Message\Close;
use WebSocket\Message\Text;
use WebSocket\Middleware\CloseHandler;
use WebSocket\Middleware\PingResponder;

class StreamConnection
{
    private ?Client $client = null;

    public function connect(string $url): void
    {
        $this->client = new Client($url);
        $this->client->setTimeout(5)->addMiddleware(new PingResponder)->addMiddleware(new CloseHandler);
        $this->client->connect();
    }

    public function receive(): ?string
    {
        $m = $this->client->receive();
        if ($m instanceof Close) {
            throw new \RuntimeException('Provider closed stream');
        }

        return $m instanceof Text ? $m->getContent() : null;
    }

    public function close(): void
    {
        if ($this->client) {
            try {
                $this->client->disconnect();
            } catch (\Throwable) {
            }$this->client = null;
        }
    }
}
