<?php
/**
 * Created by PhpStorm.
 * User: tsarikau
 * Date: 07/05/2014
 * Time: 15:23
 */

namespace FreeAgent\Bitter\UnitOfTime;


trait UnitAggregationTrait {

    public function getAggregationKey()
    {
        return sprintf('%s:%s',$this->getKey(),AggregationUnitInterface::AGGREGATION_KEY_PREFIX);
    }
}