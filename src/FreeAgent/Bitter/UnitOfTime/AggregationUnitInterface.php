<?php
/**
 * Created by PhpStorm.
 * User: tsarikau
 * Date: 07/05/2014
 * Time: 14:21
 */

namespace FreeAgent\Bitter\UnitOfTime;


interface AggregationUnitInterface extends UnitOfTimeInterface {

    const AGGREGATION_KEY_PREFIX='aggr';

    public function getAggregationKey();
} 