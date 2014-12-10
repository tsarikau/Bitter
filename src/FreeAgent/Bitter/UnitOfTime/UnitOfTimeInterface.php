<?php

namespace FreeAgent\Bitter\UnitOfTime;

use FreeAgent\Bitter\KeyInterface;

/**
 * @author Jérémy Romey <jeremy@free-agent.fr>
 */
interface UnitOfTimeInterface extends KeyInterface
{
    /**
     * @return string
     */
    public function getUnitOfTimeName();

    /**
     * @return \DateTime | null
     */
    public function getDateTime();

    /**
     * @return string
     */
    public function getDateTimeFormated();

    /**
     * @return mixed
     */
    public function getExpires();

    /**
     * @return KeyInterface
     */
    public function getKey();
}
