<?php

namespace FreeAgent\Bitter\UnitOfTime;

use FreeAgent\Bitter\EventInterface;

/**
 * @author Jérémy Romey <jeremy@free-agent.fr>
 */
interface UnitOfTimeInterface extends EventInterface
{
    public function __construct($eventName, \DateTime $dateTime);
    public function getUnitOfTimeName();
    public function getDateTime();
    public function getDateTimeFormated();
}
