<?php

namespace Lxj\Laravel\Presto\Query\Processors;

class Processor extends \Illuminate\Database\Query\Processors\Processor
{
    /**
     * Process the results of a column listing query.
     *
     * @param  array  $results
     * @return array
     */
    public function processColumnListing($results)
    {
        return array_map(function ($result) {
            return ((object) $result)->column_name;
        }, $results);
    }
}
