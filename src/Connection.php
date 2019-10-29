<?php

namespace Lxj\Laravel\Presto;

use Illuminate\Database\Events\StatementPrepared;
use Lxj\Laravel\Presto\Connectors\HttpConnector;
use Lxj\Laravel\Presto\Query\Grammars\Grammar as QueryGrammar;
use Lxj\Laravel\Presto\Schema\Grammars\Grammar as SchemaGrammar;
use SebastianBergmann\CodeCoverage\Report\PHP;
use Ytake\PrestoClient\ClientSession;
use Ytake\PrestoClient\FixData;
use Ytake\PrestoClient\QueryResult;
use Ytake\PrestoClient\ResultsSession;
use Ytake\PrestoClient\Session\PreparedStatement;
use Ytake\PrestoClient\StatementClient;

class Connection extends \Illuminate\Database\Connection
{
    public function __construct($pdo, $database = '', $tablePrefix = '', array $config = [])
    {
        parent::__construct($pdo, $database, $tablePrefix, $config);
    }

    public function select($query, $bindings = [], $useReadPdo = true)
    {
        return $this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo) {
            if ($this->pretending()) {
                return [];
            }

            $clientSession = (new HttpConnector())->connect($this->config);
            
            $prepareName = 'my_select';

            $this->prepareQuery($clientSession, $query, $prepareName, $useReadPdo);

            $executeQuery = 'EXECUTE ' . $prepareName;
            if (count($bindings) > 0) {
                $executeQuery .= (' USING ' . implode(', ', $this->prepareBindings($bindings)));
            }
            $statement = $this->getStatement($clientSession, $executeQuery, $useReadPdo);

            $this->afterPrepare($statement);

            $result = [];
            $queryResults = $this->getResultSession($statement)->execute()->yieldResults();
            foreach ($queryResults as $queryResult) {
                if ($queryResult instanceof QueryResult) {
                    foreach ($queryResult->yieldData() as $row) {
                        if ($row instanceof FixData) {
                            $result[] = (array)$row;
                        }
                    }
                }
            }

            return $result;
        });
    }

    protected function prepareQuery(ClientSession $clientSession, $query, $prepareName, $useReadPdo = true)
    {
        $preparedStatement = new PreparedStatement($prepareName, $query);
        $clientSession->setPreparedStatement($preparedStatement);
    }

    protected function getStatement(ClientSession $clientSession, $query, $useReadPdo = true)
    {
        return new StatementClient($clientSession, $query);
    }

    protected function afterPrepare($statement)
    {
        $this->event(new StatementPrepared(
            $this, $statement
        ));
    }

    protected function getResultSession($statement)
    {
        return (new ResultsSession(
            $statement,
            $this->config['timeout'] ?? 500000,
            $this->config['debug'] ?? false
        ));
    }

    protected function getDefaultQueryGrammar()
    {
        return $this->withTablePrefix(new QueryGrammar());
    }

    protected function getDefaultSchemaGrammar()
    {
        return $this->withTablePrefix(new SchemaGrammar());
    }

    protected function queryBinding($query, $bindings)
    {
        $bindings = $this->prepareBindings($bindings);
        $query = strtr($query, $bindings);
        return $query;
    }
}
