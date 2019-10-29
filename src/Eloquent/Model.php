<?php

namespace Lxj\Laravel\Presto\Eloquent;

class Model extends \Illuminate\Database\Eloquent\Model
{
    protected $connection = 'presto';
    
    public $incrementing = false;
}
