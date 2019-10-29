<?php

namespace Lxj\Laravel\Presto\Schema\Grammars;

class Grammar extends \Illuminate\Database\Schema\Grammars\Grammar
{
    /**
     * Wrap a single string in keyword identifiers.
     *
     * @param  string  $value
     * @return string
     */
    protected function wrapValue($value)
    {
        if ($value !== '*') {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }
}
