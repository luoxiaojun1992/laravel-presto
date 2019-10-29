<?php

namespace Lxj\Laravel\Presto\Connectors;

use Illuminate\Database\Connectors\Connector;
use Illuminate\Database\Connectors\ConnectorInterface;
use Ytake\PrestoClient\ClientSession;

class HttpConnector extends Connector implements ConnectorInterface
{
    public function connect(array $config)
    {
        $clientSession = new ClientSession($config['host'], $config['catalog']);
        $clientSession->setSchema($config['schema']);
        
        //todo auth?
        
        return $clientSession;
    }
}
