<?php
namespace FreeAgent\Bitter;

use FreeAgent\Bitter\UnitOfTime\Day;
use FreeAgent\Bitter\UnitOfTime\Hour;
use FreeAgent\Bitter\UnitOfTime\Month;
use FreeAgent\Bitter\UnitOfTime\Week;
use FreeAgent\Bitter\UnitOfTime\Year;

class Event implements EventInterface
{

    /**
     * @var
     */
    protected $key;

    /**
     * @param $key
     */
    public function construct($key)
    {
        $this->key = $key;
    }

    /**
     * @return mixed
     */
    public function getKey()
    {
        return $this->key;
    }


    /**
     * @param \DateTime $dateTime
     * @return array|UnitOfTime\UnitOfTimeInterface[]
     */
    public function getUnitsOfTime(\DateTime $dateTime)
    {
        return array(
            'year' => new Year($this, $dateTime),
            'month' => new Month($this, $dateTime),
            'week' => new Week($this, $dateTime),
            'day' => new Day($this, $dateTime),
            'hour' => new Hour($this, $dateTime),
        );
    }

    /**
     * @param $event
     * @return static
     */
    public static function create($event)
    {
        if ($event instanceof static) {
            return $event;
        }

        return new static($event);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getKey();
    }
}