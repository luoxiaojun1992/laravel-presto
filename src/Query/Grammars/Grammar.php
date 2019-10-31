<?php

namespace Lxj\Laravel\Presto\Query\Grammars;

class Grammar extends \Illuminate\Database\Query\Grammars\Grammar
{
    /**
     * Wrap a value in keyword identifiers.
     *
     * @param  \Illuminate\Database\Query\Expression|string  $value
     * @param  bool    $prefixAlias
     * @return string
     */
    public function wrap($value, $prefixAlias = false)
    {
        if ($this->isExpression($value)) {
            return $this->getValue($value);
        }

        // If the value being wrapped has a column alias we will need to separate out
        // the pieces so we can wrap each of the segments of the expression on its
        // own, and then join these both back together using the "as" connector.
        if (stripos($value, ' as ') !== false) {
            return $this->wrapAliasedValue($value, $prefixAlias);
        }

        return $this->wrapValue($value);
    }
}
