<?php

namespace FreeAgent\Bitter;

use FreeAgent\Bitter\UnitOfTime\UnitOfTimeInterface;

interface EventInterface {

    /**
     * @return mixed
     */
    public function getKey();

    /**
     * @param \DateTime $dateTime
     * @return UnitOfTimeInterface[]
     */
    public function getUnitsOfTime(\DateTime $dateTime);
}