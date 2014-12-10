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

    public function getExpires()
    {
        return null;
    }
}
