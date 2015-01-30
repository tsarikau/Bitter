<?php
/**
 * Created by PhpStorm.
 * User: tsarikau
 * Date: 05/05/2014
 * Time: 17:21
 */

namespace FreeAgent\Bitter\UnitOfTime;

class Infinity extends AbstractUnitOfTime
{

    public function getDateTimeFormated()
    {
        return 'inf';
    }

    /** @return \DateInterval */
    public function getInterval()
    {
        return null;
    }


    public function getExpires()
    {
        return null;
    }
}
