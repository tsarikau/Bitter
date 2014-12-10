<?php
/**
 * Created by PhpStorm.
 * User: tsarikau
 * Date: 04/12/2014
 * Time: 14:38
 */

namespace FreeAgent\Bitter\Aggregation;

class AggregationKey implements AggregationKeyInterface
{

    protected $key;

    public function __construct($key)
    {
        $this->key = $key;
    }

    public function __toString()
    {
        return sprintf("%s:%s", (string)$this->key, 'aggr');
    }
}
