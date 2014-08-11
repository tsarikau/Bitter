<?php

namespace FreeAgent\Bitter;

use \DateTime;
use \Exception;
use FreeAgent\Bitter\Date\DatePeriod;
use FreeAgent\Bitter\UnitOfTime\AggregationUnitInterface;
use FreeAgent\Bitter\UnitOfTime\DayAggregation;
use FreeAgent\Bitter\UnitOfTime\HourAggregation;
use FreeAgent\Bitter\UnitOfTime\MonthAggregation;
use FreeAgent\Bitter\UnitOfTime\Year;
use FreeAgent\Bitter\UnitOfTime\Month;
use FreeAgent\Bitter\UnitOfTime\Week;
use FreeAgent\Bitter\UnitOfTime\Day;
use FreeAgent\Bitter\UnitOfTime\Hour;
use FreeAgent\Bitter\UnitOfTime\UnitOfTimeInterface;
use FreeAgent\Bitter\UnitOfTime\YearAggregation;

/**
 * @author Jérémy Romey <jeremy@free-agent.fr>
 */
class Bitter
{
    private $redisClient;
    private $prefixKey;
    private $prefixTempKey;
    private $expireTimeout;

    public function __construct(
        $redisClient,
        $prefixKey = 'bitter:',
        $prefixTempKey = 'bitter_temp:',
        $expireTimeout = 60
    ) {
        $this->setRedisClient($redisClient);
        $this->prefixKey = $prefixKey;
        $this->prefixTempKey = $prefixTempKey;
        $this->expireTimeout = $expireTimeout;
    }

    /**
     * Get the Redis client
     *
     * @return The Redis client
     */
    public function getRedisClient($client = null)
    {
        if ($client) {
            return $client;
        }

        return $this->redisClient;
    }

    /**
     * Set the Redis client
     *
     * @param object $newredisClient The Redis client
     */
    public function setRedisClient($redisClient)
    {
        $this->redisClient = $redisClient;

        return $this;
    }

    /**
     * Marks an event for hours, days, weeks and months
     *
     * @param string $eventName The name of the event, could be "active" or "new_signups"
     * @param integer $id An unique id, typically user id. The id should not be huge, read Redis documentation why (bitmaps)
     * @param DateTime $dateTime Which date should be used as a reference point, default is now
     */
    public function mark($event, $id, DateTime $dateTime = null)
    {
        $dateTime = is_null($dateTime) ? new DateTime : $dateTime;

        $event = $event instanceof EventInterface ? $event : new Event($event, $dateTime);

        $this->getRedisClient()->pipeline(
            function ($pipe) use ($event, $id, $dateTime) {
                foreach ($event->getUnitsOfTime($dateTime) as $unit) {
                    $key = null;

                    if ($unit instanceof AggregationUnitInterface) {
                        $key = $this->incrScore($unit, $id, $pipe);
                    } else {
                        $key = $this->setBit($unit, $id, $pipe);
                    }
                    if ($expires = $unit->getExpires()) {
                        if ($expires instanceof DateTime) {
                            $this->getRedisClient($pipe)->expireat($key, $expires->getTimestamp());
                        } else {
                            $this->getRedisClient($pipe)->expire($key, $expires);
                        }
                    }
                    $this->getRedisClient($pipe)->sadd($this->prefixKey . 'keys', $key);
                }
            }
        );

        return $this;
    }

    protected function setBit(UnitOfTimeInterface $unit, $id, $client = null)
    {
        $key = $this->prefixKey . $unit->getKey();
        $this->getRedisClient($client)->setbit($key, $id, 1);
        return $key;
    }


    protected function incrScore(AggregationUnitInterface $unit, $id, $client = null)
    {
        $key = $this->prefixKey . $unit->getAggregationKey();
        $this->getRedisClient($client)->zincrby($key, 1.0, $id);
        return $key;
    }

    /**
     * Makes it possible to see if an id has been marked
     *
     * @param  integer $id An unique id
     * @param  mixed $key The key or the event
     * @return boolean True if the id has been marked
     */
    public function in($id, $key)
    {
        if ($key instanceof AggregationUnitInterface) {
            $key = $this->prefixKey . $key->getAggregationKey();
            return (bool)$this->getRedisClient()->zscore($key, $id);
        }

        $key = $key instanceof UnitOfTimeInterface ? $this->prefixKey . $key->getKey() : $this->prefixTempKey . $key;

        return (bool)$this->getRedisClient()->getbit($key, $id);
    }

    /**
     * Counts the number of marks
     *
     * @param  mixed $key The key or the event
     * @return integer The value of the count result
     */
    public function count($key, $id = null)
    {
        if ($key instanceof AggregationUnitInterface) {
            $key = $this->prefixKey . $key->getAggregationKey();
            if ($id) {
                return (int)$this->getRedisClient()->zscore($key, $id);
            }
            $aggregation = 0;
            foreach ($this->getRedisClient()->zrange($key, 0, -1, 'WITHSCORES') as $score) {
                $aggregation += (int)$score;
            }

            return $aggregation;
        }

        $key = $key instanceof UnitOfTimeInterface ? $this->prefixKey . $key->getKey() : $this->prefixTempKey . $key;

        return (int)$this->getRedisClient()->bitcount($key);
    }

    private function bitOp($op, $destKey, $keyOne, $keyTwo)
    {
        $keyOne = $keyOne instanceof UnitOfTimeInterface ? $this->prefixKey . $keyOne->getKey(
            ) : $this->prefixTempKey . $keyOne;
        $keyTwo = $keyTwo instanceof UnitOfTimeInterface ? $this->prefixKey . $keyTwo->getKey(
            ) : $this->prefixTempKey . $keyTwo;

        $this->getRedisClient()->bitop($op, $this->prefixTempKey . $destKey, $keyOne, $keyTwo);
        $this->getRedisClient()->sadd($this->prefixTempKey . 'keys', $destKey);
        $this->getRedisClient()->expire($destKey, $this->expireTimeout);

        return $this;
    }

    public function bitOpAnd($destKey, $keyOne, $keyTwo)
    {
        return $this->bitOp('AND', $destKey, $keyOne, $keyTwo);
    }

    public function bitOpOr($destKey, $keyOne, $keyTwo)
    {
        return $this->bitOp('OR', $destKey, $keyOne, $keyTwo);
    }

    public function bitOpXor($destKey, $keyOne, $keyTwo)
    {
        return $this->bitOp('XOR', $destKey, $keyOne, $keyTwo);
    }

    public function bitDateRange($key, $destKey, DateTime $from, DateTime $to)
    {
        if ($from > $to) {
            throw new Exception("DateTime from (" . $from->format(
                'Y-m-d H:i:s'
            ) . ") must be anterior to DateTime to (" . $to->format('Y-m-d H:i:s') . ").");
        }

        $this->getRedisClient()->del($this->prefixTempKey . $destKey);

        // Hours
        $hoursFrom = DatePeriod::createForHour($from, $to, DatePeriod::CREATE_FROM);
        foreach ($hoursFrom as $date) {
            $this->bitOpOr($destKey, new Hour($key, $date), $destKey);
        }
        $hoursTo = DatePeriod::createForHour($from, $to, DatePeriod::CREATE_TO);
        if (array_diff($hoursTo->toArray(true), $hoursFrom->toArray(true)) !== array_diff(
                $hoursFrom->toArray(true),
                $hoursTo->toArray(true)
            )
        ) {
            foreach ($hoursTo as $date) {
                $this->bitOpOr($destKey, new Hour($key, $date), $destKey);
            }
        }

        // Days
        $daysFrom = DatePeriod::createForDay($from, $to, DatePeriod::CREATE_FROM);
        foreach ($daysFrom as $date) {
            $this->bitOpOr($destKey, new Day($key, $date), $destKey);
        }
        $daysTo = DatePeriod::createForDay($from, $to, DatePeriod::CREATE_TO);
        if (array_diff($daysTo->toArray(true), $daysFrom->toArray(true)) !== array_diff(
                $daysFrom->toArray(true),
                $daysTo->toArray(true)
            )
        ) {
            foreach ($daysTo as $date) {
                $this->bitOpOr($destKey, new Day($key, $date), $destKey);
            }
        }

        // Months
        $monthsFrom = DatePeriod::createForMonth($from, $to, DatePeriod::CREATE_FROM);
        foreach ($monthsFrom as $date) {
            $this->bitOpOr($destKey, new Month($key, $date), $destKey);
        }
        $monthsTo = DatePeriod::createForMonth($from, $to, DatePeriod::CREATE_TO);
        if (array_diff($monthsTo->toArray(true), $monthsFrom->toArray(true)) !== array_diff(
                $monthsFrom->toArray(true),
                $monthsTo->toArray(true)
            )
        ) {
            foreach ($monthsTo as $date) {
                $this->bitOpOr($destKey, new Month($key, $date), $destKey);
            }
        }

        // Years
        $years = DatePeriod::createForYear($from, $to);
        foreach ($years as $date) {
            $this->bitOpOr($destKey, new Year($key, $date), $destKey);
        }

        $this->getRedisClient()->sadd($this->prefixTempKey . 'keys', $destKey);
        $this->getRedisClient()->expire($destKey, $this->expireTimeout);

        return $this;
    }


    public function aggregationDateRange($key, $destKey, DateTime $from, DateTime $to = null)
    {
        if (!$to) {
            $to = new DateTime();
        }
        if ($from > $to) {
            throw new Exception("DateTime from (" . $from->format(
                'Y-m-d H:i:s'
            ) . ") must be anterior to DateTime to (" . $to->format('Y-m-d H:i:s') . ").");
        }
        $rc = $this->getRedisClient();

        $rc->del($this->prefixTempKey . $destKey);

        $aggregation_keys = array();

        // Hours
        $hoursFrom = DatePeriod::createForHour($from, $to, DatePeriod::CREATE_FROM);
        foreach ($hoursFrom as $date) {
            $aggregation_keys[] = HourAggregation::create($key, $date)->getAggregationKey();
        }
        $hoursTo = DatePeriod::createForHour($from, $to, DatePeriod::CREATE_TO);
        if (array_diff($hoursTo->toArray(true), $hoursFrom->toArray(true)) !== array_diff(
                $hoursFrom->toArray(true),
                $hoursTo->toArray(true)
            )
        ) {
            foreach ($hoursTo as $date) {
                $aggregation_keys[] = HourAggregation::create($key, $date)->getAggregationKey();
            }
        }

        // Days
        $daysFrom = DatePeriod::createForDay($from, $to, DatePeriod::CREATE_FROM);
        foreach ($daysFrom as $date) {
            $aggregation_keys[] = DayAggregation::create($key, $date)->getAggregationKey();
        }
        $daysTo = DatePeriod::createForDay($from, $to, DatePeriod::CREATE_TO);
        if (array_diff($daysTo->toArray(true), $daysFrom->toArray(true)) !== array_diff(
                $daysFrom->toArray(true),
                $daysTo->toArray(true)
            )
        ) {
            foreach ($daysTo as $date) {
                $aggregation_keys[] = DayAggregation::create($key, $date)->getAggregationKey();
            }
        }

        // Months
        $monthsFrom = DatePeriod::createForMonth($from, $to, DatePeriod::CREATE_FROM);
        foreach ($monthsFrom as $date) {
            $aggregation_keys[] = MonthAggregation::create($key, $date)->getAggregationKey();
        }
        $monthsTo = DatePeriod::createForMonth($from, $to, DatePeriod::CREATE_TO);
        if (array_diff($monthsTo->toArray(true), $monthsFrom->toArray(true)) !== array_diff(
                $monthsFrom->toArray(true),
                $monthsTo->toArray(true)
            )
        ) {
            foreach ($monthsTo as $date) {
                $aggregation_keys[] = MonthAggregation::create($key, $date)->getAggregationKey();
            }
        }

        // Years
        $years = DatePeriod::createForYear($from, $to);
        foreach ($years as $date) {
            $aggregation_keys[] = YearAggregation::create($key, $date)->getAggregationKey();
        }

        $arguments = array($this->prefixTempKey . $destKey, count($aggregation_keys));

        foreach ($aggregation_keys as $k) {
            array_push($arguments, $this->prefixKey . $k);
        }

        call_user_func_array(array($rc, 'zunionstore'), $arguments);

        $this->getRedisClient()->sadd($this->prefixTempKey . 'keys', $destKey);
        $this->getRedisClient()->expire($destKey, $this->expireTimeout);

        return $this;
    }


    /**
     * Returns the ids of an key or event
     *
     * @param  mixed $key The key or the event
     * @return array   The ids array
     */
    public function getIds($key, $with_scores = null)
    {
        if ($key instanceof AggregationKey) {
            $key = $this->prefixTempKey . $key;
            if (!$with_scores) {
                return $this->getRedisClient()->zrange($key, 0, -1);
            }
            $result = array();
            foreach ($this->getRedisClient()->zrange($key, 0, -1, 'WITHSCORES') as $member=>$score) {
                $result[] = array($member,$score);
            }
            return $result;
        }

        $key = $key instanceof UnitOfTimeInterface ? $this->prefixKey . $key->getKey() : $this->prefixTempKey . $key;

        $string = $this->getRedisClient()->get($key);

        $data = $this->bitsetToString($string);

        $ids = array();
        while (false !== ($pos = strpos($data, '1'))) {
            $data[$pos] = 0;
            $ids[] = (int)($pos / 8) * 8 + abs(7 - ($pos % 8));
        }

        sort($ids);

        return $ids;
    }

    protected function bitsetToString($bitset = '')
    {
        return bitset_to_string($bitset);
    }

    /**
     * Removes all Bitter keys
     */
    public function removeAll()
    {
        $keys_chunk = array_chunk($this->getRedisClient()->smembers($this->prefixKey . 'keys'), 100);

        foreach ($keys_chunk as $keys) {
            $this->getRedisClient()->del($keys);
        }

        return $this;
    }

    /**
     * Removes all Bitter temp keys
     */
    public function removeTemp()
    {
        $keys_chunk = array_chunk($this->getRedisClient()->smembers($this->prefixTempKey . 'keys'), 100);

        foreach ($keys_chunk as $keys) {
            $this->getRedisClient()->del($keys);
        }

        return $this;
    }
}
