<?php
/**
 * Created by PhpStorm.
 * User: tsarikau
 * Date: 04/12/2014
 * Time: 14:02
 */

namespace FreeAgent\Bitter\Aggregation;

use FreeAgent\Bitter\UnitOfTime\AbstractUnitOfTime;
use FreeAgent\Bitter\UnitOfTime\UnitOfTimeInterface;

class Aggregation implements UnitOfTimeInterface, AggregationKeyInterface
{

    protected $inner;

    public function __construct(AbstractUnitOfTime $inner)
    {
        $this->inner = $inner;
    }

    public static function create($unit)
    {
        return new static($unit);
    }

    public function getUnitOfTimeName()
    {
        return $this->inner->getUnitOfTimeName();
    }

    public function getDateTime()
    {
        return $this->inner->getDateTime();
    }

    public function getExpires()
    {
        return $this->inner->getExpires();
    }

    public function getKey()
    {
        return new AggregationKey($this->inner->getKey());
    }

    public function getDateTimeFormated()
    {
        return $this->inner->getDateTimeFormated();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getKey()->__toString();
    }
}
