<?php

namespace FreeAgent\Bitter\UnitOfTime;

use \DateTime;
use FreeAgent\Bitter\EventInterface;

/**
 * @author Jérémy Romey <jeremy@free-agent.fr>
 */
abstract class AbstractUnitOfTime
{
    protected $eventName;
    protected $dateTime;
    protected $expires=null;

    public function __construct($eventName, DateTime $dateTime = null)
    {
        $this->eventName = $eventName instanceof EventInterface ? $eventName->getKey() : $eventName;
        $this->dateTime  = is_null($dateTime) ? new DateTime : $dateTime;
    }

    public function getUnitOfTimeName()
    {
        return $this->eventName;
    }

    public function getDateTime()
    {
        return $this->dateTime;
    }

    public function setExpires($dateTime=null){
        $this->expires=$dateTime;
        return $this;
    }

    public function getExpires(){
        return $this->expires;
    }

    public static function create($eventName, DateTime $dateTime = null){
        return new static($eventName,$dateTime);
    }

    abstract public function getDateTimeFormated();

    public function getKey()
    {
        return sprintf('%s:%s', $this->getUnitOfTimeName(), $this->getDateTimeFormated());
    }
}
