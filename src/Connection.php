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

    protected function reconnectIfMissingConnection()
    {
        //
    }
    
    public function reconnect()
    {
        //
    }

    public function select($query, $bindings = [], $useReadPdo = true)
    {
        return $this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo) {
            if ($this->pretending()) {
                return [];
            }

            $clientSession = (new HttpConnector())->connect($this->config);

            $prepareName = 'prepared_statement';

            $statement = $this->prepareQuery($clientSession, $query, $prepareName, $bindings, $useReadPdo);

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

    public function cursor($query, $bindings = [], $useReadPdo = true)
    {
        return $this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo) {
            if ($this->pretending()) {
                return [];
            }

            $clientSession = (new HttpConnector())->connect($this->config);

            $prepareName = 'prepared_statement';

            $statement = $this->prepareQuery($clientSession, $query, $prepareName, $bindings, $useReadPdo);

            $this->afterPrepare($statement);

            $result = [];
            $queryResults = $this->getResultSession($statement)->execute()->yieldResults();
            foreach ($queryResults as $queryResult) {
                if ($queryResult instanceof QueryResult) {
                    foreach ($queryResult->yieldData() as $row) {
                        if ($row instanceof FixData) {
                            yield (array)$row;
                        }
                    }
                }
            }
        });
    }

    /**
     * Run an SQL statement and get the number of rows affected.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return int
     */
    public function affectingStatement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return 0;
            }

            $clientSession = (new HttpConnector())->connect($this->config);

            $prepareName = 'prepared_statement';

            $statement = $this->prepareQuery($clientSession, $query, $prepareName, $bindings);

            $this->afterPrepare($statement);

            $queryResults = $this->getResultSession($statement)->execute()->yieldResults();
            foreach ($queryResults as $queryResult) {
                if ($queryResult instanceof QueryResult) {
                    foreach ($queryResult->yieldData() as $row) {
                        if ($row instanceof FixData) {
                            if (isset($row['rows'])) {
                                $this->recordsHaveBeenModified(
                                    ($count = $row['rows']) > 0
                                );

                                return $count;
                            }
                        }
                    }
                }
            }

            $this->recordsHaveBeenModified(false);

            return 0;
        });
    }

    /**
     * Execute an SQL statement and return the boolean result.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return bool
     */
    public function statement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return true;
            }

            $clientSession = (new HttpConnector())->connect($this->config);

            $prepareName = 'prepared_statement';

            $statement = $this->prepareQuery($clientSession, $query, $prepareName, $bindings);

            $this->afterPrepare($statement);

            $queryResults = $this->getResultSession($statement)->execute()->yieldResults();
            foreach ($queryResults as $queryResult) {
                if ($queryResult instanceof QueryResult) {
                    foreach ($queryResult->yieldData() as $row) {
                        if ($row instanceof FixData) {
                            if (isset($row['rows'])) {
                                $this->recordsHaveBeenModified(
                                    ($count = $row['rows']) > 0
                                );

                                return $count > 0;
                            }
                        }
                    }
                }
            }

            $this->recordsHaveBeenModified(false);

            return false;
        });
    }

    /**
     * Run a raw, unprepared query against the PDO connection.
     *
     * @param  string  $query
     * @return bool
     */
    public function unprepared($query)
    {
        return $this->run($query, [], function ($query) {
            if ($this->pretending()) {
                return true;
            }

            $clientSession = (new HttpConnector())->connect($this->config);

            $statement = $this->getStatement($clientSession, $query);

            $this->afterPrepare($statement);

            $queryResults = $this->getResultSession($statement)->execute()->yieldResults();
            foreach ($queryResults as $queryResult) {
                if ($queryResult instanceof QueryResult) {
                    foreach ($queryResult->yieldData() as $row) {
                        if ($row instanceof FixData) {
                            if (isset($row['rows'])) {
                                $this->recordsHaveBeenModified(
                                    ($count = $row['rows']) > 0
                                );

                                return $count > 0;
                            }
                        }
                    }
                }
            }

            $this->recordsHaveBeenModified(false);

            return false;
        });
    }

    protected function prepareQuery(
        ClientSession $clientSession,
        $query,
        $prepareName,
        $bindings = [],
        $useReadPdo = false
    ) {
        $preparedStatement = new PreparedStatement($prepareName, $query);
        $clientSession->setPreparedStatement($preparedStatement);

        $executeQuery = 'EXECUTE ' . $prepareName;
        if (count($bindings) > 0) {
            $executeQuery .= (' USING ' . implode(', ', $this->prepareBindings($bindings)));
        }

        return $this->getStatement($clientSession, $executeQuery);
    }

    /**
     * Prepare the query bindings for execution.
     *
     * @param  array  $bindings
     * @return array
     */
    public function prepareBindings(array $bindings)
    {
        $bindings = parent::prepareBindings($bindings);

        foreach ($bindings as $key => $value) {
            if (is_string($value)) {
                $bindings[$key] = '\'' . $value . '\'';
            }
        }

        return $bindings;
    }

    protected function getStatement(ClientSession $clientSession, $query, $useReadPdo = false)
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
