<?php

namespace FreeAgent\Bitter\UnitOfTime;

use DateTime;
use FreeAgent\Bitter\EventInterface;
use FreeAgent\Bitter\Key;

/**
 * @author Jérémy Romey <jeremy@free-agent.fr>
 */
abstract class AbstractUnitOfTime implements UnitOfTimeInterface
{
    protected $eventName;
    protected $dateTime;
    protected $expires = null;

    public function __construct($eventName, DateTime $dateTime = null)
    {
        $this->eventName = $eventName instanceof EventInterface ? $eventName->getKey() : $eventName;
        $this->dateTime = is_null($dateTime) ? new DateTime : $dateTime;
    }

    /**
     * @return mixed
     */
    public function getUnitOfTimeName()
    {
        return $this->eventName;
    }

    /**
     * @return DateTime
     */
    public function getDateTime()
    {
        return $this->dateTime;
    }

    /**
     * @param null $dateTime
     * @return $this
     */
    public function setExpires($dateTime = null)
    {
        $this->expires = $dateTime;

        return $this;
    }

    /**
     * @return null
     */
    public function getExpires()
    {
        return $this->expires;
    }

    /**
     * @param $eventName
     * @param DateTime $dateTime
     * @return static
     */
    public static function create($eventName, DateTime $dateTime = null)
    {
        return new static($eventName, $dateTime);
    }

    /**
     * @param DateTime $dateTime
     * @return AbstractUnitOfTime
     */
    public function modify(\DateTime $dateTime = null)
    {
        return static::create($this->eventName, $dateTime);
    }

    /**
     * @return mixed
     */
    abstract public function getDateTimeFormated();

    /**
     * @return Key
     */
    public function getKey()
    {
        return new Key(sprintf('%s:%s', $this->getUnitOfTimeName(), $this->getDateTimeFormated()));
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getKey()->__toString();
    }
}
