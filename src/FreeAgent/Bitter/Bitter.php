<?php

namespace FreeAgent\Bitter;

use \DateTime;
use \Exception;
use FreeAgent\Bitter\Aggregation\Aggregation;
use FreeAgent\Bitter\Aggregation\AggregationKey;
use FreeAgent\Bitter\Aggregation\AggregationKeyInterface;
use FreeAgent\Bitter\Date\DatePeriod;
use FreeAgent\Bitter\UnitOfTime\Year;
use FreeAgent\Bitter\UnitOfTime\Month;
use FreeAgent\Bitter\UnitOfTime\Week;
use FreeAgent\Bitter\UnitOfTime\Day;
use FreeAgent\Bitter\UnitOfTime\Hour;
use FreeAgent\Bitter\UnitOfTime\UnitOfTimeInterface;

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
     * @param null $client
     * @return mixed
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
     * @param $redisClient
     * @return $this
     */
    public function setRedisClient($redisClient)
    {
        $this->redisClient = $redisClient;

        return $this;
    }

    /**
     * Marks an event for hours, days, weeks and months
     *
     * @param $event
     * @param integer $id An unique id, typically user id. The id should not be huge, read Redis documentation why (bitmaps)
     * @param DateTime $dateTime Which date should be used as a reference point, default is now
     * @return $this
     */
    public function mark($event, $id, DateTime $dateTime = null)
    {
        $dateTime = is_null($dateTime) ? new DateTime : $dateTime;

        $event = $event instanceof EventInterface ? $event : new Event($event, $dateTime);

        $this->getRedisClient()->pipeline(
            array(
                'fire-and-forget' => true,
            ),
            function ($pipe) use ($event, $id, $dateTime) {

                $now = new \DateTime();

                foreach ($event->getUnitsOfTime($dateTime) as $unit) {

                    $expires = $unit->getExpires();

                    //key will be removed immediately in such case, so why not stop it from write?
                    if ($expires && $expires instanceof DateTime && $now > $expires) {
                        continue;
                    }

                    $key = $this->hash($unit);

                    //set bit or increment score on time unit's key
                    if ($unit instanceof AggregationKeyInterface) {
                        $pipe->zincrby($key, 1.0, $id);
                    } elseif ($unit instanceof KeyInterface) {
                        $pipe->setbit($key, $id, 1);
                    } else {
                        throw new \Exception('Aggregation or Bit keys only');
                    }

                    //set expires for time unit's key, if provided
                    if ($expires) {
                        if ($expires instanceof DateTime) {
                            $pipe->expireat($key, $expires->getTimestamp());
                        } else {
                            $pipe->expire($key, $expires);
                        }
                    }

                    $pipe->sadd($this->prefixKey . 'keys', $key);
                }
            }
        );

        return $this;
    }

    /**
     * Makes it possible to see if an id has been marked
     *
     * @param  integer $id An unique id
     * @param  mixed $key The key or the event
     * @throws \Exception
     * @return boolean True if the id has been marked
     */
    public function in($id, KeyInterface $key)
    {
        if ($key instanceof AggregationKeyInterface) {
            return (bool)$this->getRedisClient()->zscore($this->hash($key), $id);
        }

        return (bool)$this->getRedisClient()->getbit($this->hash($key), $id);

    }

    /**
     * Counts the number of marks
     *
     * @param  mixed $key The key or the event
     * @param null $id
     * @throws \Exception
     * @return integer The value of the count result
     */
    public function count(KeyInterface $key, $id = null)
    {
        if ($key instanceof AggregationKeyInterface) {

            if ($id) {
                return (int)$this->getRedisClient()->zscore($this->hash($key), $id);
            }

            $aggregation = 0;
            foreach ($this->getRedisClient()->zrange($this->hash($key), 0, -1, 'WITHSCORES') as $score) {
                $aggregation += (int)$score;
            }

            return $aggregation;
        }

        return (int)$this->getRedisClient()->bitcount($this->hash($key));
    }

    private function bitOp($op, $destKey, $keyOne, $keyTwo)
    {
        $destKey = $this->hash($destKey);
        $keyOne = $this->hash($keyOne);
        $keyTwo = $this->hash($keyTwo);

        $this->getRedisClient()->bitop($op, $destKey, $keyOne, $keyTwo);
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

    /**
     * @param $event
     * @param Key $destKey
     * @param DateTime $from
     * @param DateTime $to
     * @return $this
     * @throws \Exception
     */
    public function bitDateRange(EventInterface $event, Key $destKey, DateTime $from, DateTime $to)
    {
        if ($from > $to) {
            throw new Exception("DateTime from (" . $from->format(
                'Y-m-d H:i:s'
            ) . ") must be anterior to DateTime to (" . $to->format('Y-m-d H:i:s') . ").");
        }

        $this->getRedisClient()->del($this->hash($destKey));

        // Hours
        $hoursFrom = DatePeriod::createForHour($from, $to, DatePeriod::CREATE_FROM);
        foreach ($hoursFrom as $date) {
            $this->bitOpOr($destKey, Hour::create($event, $date), $destKey);
        }
        $hoursTo = DatePeriod::createForHour($from, $to, DatePeriod::CREATE_TO);
        if (array_diff($hoursTo->toArray(true), $hoursFrom->toArray(true)) !== array_diff(
                $hoursFrom->toArray(true),
                $hoursTo->toArray(true)
            )
        ) {
            foreach ($hoursTo as $date) {
                $this->bitOpOr($destKey, Hour::create($event, $date), $destKey);
            }
        }

        // Days
        $daysFrom = DatePeriod::createForDay($from, $to, DatePeriod::CREATE_FROM);
        foreach ($daysFrom as $date) {
            $this->bitOpOr($destKey, Day::create($event, $date), $destKey);
        }
        $daysTo = DatePeriod::createForDay($from, $to, DatePeriod::CREATE_TO);
        if (array_diff($daysTo->toArray(true), $daysFrom->toArray(true)) !== array_diff(
                $daysFrom->toArray(true),
                $daysTo->toArray(true)
            )
        ) {
            foreach ($daysTo as $date) {
                $this->bitOpOr($destKey, Day::create($event, $date), $destKey);
            }
        }

        // Months
        $monthsFrom = DatePeriod::createForMonth($from, $to, DatePeriod::CREATE_FROM);
        foreach ($monthsFrom as $date) {
            $this->bitOpOr($destKey, Month::create($event, $date), $destKey);
        }
        $monthsTo = DatePeriod::createForMonth($from, $to, DatePeriod::CREATE_TO);
        if (array_diff($monthsTo->toArray(true), $monthsFrom->toArray(true)) !== array_diff(
                $monthsFrom->toArray(true),
                $monthsTo->toArray(true)
            )
        ) {
            foreach ($monthsTo as $date) {
                $this->bitOpOr($destKey, Month::create($event, $date), $destKey);
            }
        }

        // Years
        $years = DatePeriod::createForYear($from, $to);
        foreach ($years as $date) {
            $this->bitOpOr($destKey, Year::create($event, $date), $destKey);
        }

        $this->getRedisClient()->sadd($this->prefixTempKey . 'keys', $this->hash($destKey));
        $this->getRedisClient()->expire($this->hash($destKey), $this->expireTimeout);

        return $this;
    }


    public function aggregationDateRange($event, AggregationKey $destKey, DateTime $from, DateTime $to = null)
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

        $rc->del($this->hash($destKey));

        $aggregation_keys = array();

        // Hours
        $hoursFrom = DatePeriod::createForHour($from, $to, DatePeriod::CREATE_FROM);
        foreach ($hoursFrom as $date) {
            $aggregation_keys[] = Aggregation::create(Hour::create($event, $date));
        }
        $hoursTo = DatePeriod::createForHour($from, $to, DatePeriod::CREATE_TO);
        if (array_diff($hoursTo->toArray(true), $hoursFrom->toArray(true)) !== array_diff(
                $hoursFrom->toArray(true),
                $hoursTo->toArray(true)
            )
        ) {
            foreach ($hoursTo as $date) {
                $aggregation_keys[] = Aggregation::create(Hour::create($event, $date));
            }
        }

        // Days
        $daysFrom = DatePeriod::createForDay($from, $to, DatePeriod::CREATE_FROM);
        foreach ($daysFrom as $date) {
            $aggregation_keys[] = Aggregation::create(Day::create($event, $date));
        }
        $daysTo = DatePeriod::createForDay($from, $to, DatePeriod::CREATE_TO);
        if (array_diff($daysTo->toArray(true), $daysFrom->toArray(true)) !== array_diff(
                $daysFrom->toArray(true),
                $daysTo->toArray(true)
            )
        ) {
            foreach ($daysTo as $date) {
                $aggregation_keys[] = Aggregation::create(Day::create($event, $date));
            }
        }

        // Months
        $monthsFrom = DatePeriod::createForMonth($from, $to, DatePeriod::CREATE_FROM);
        foreach ($monthsFrom as $date) {
            $aggregation_keys[] = Aggregation::create(Month::create($event, $date));
        }
        $monthsTo = DatePeriod::createForMonth($from, $to, DatePeriod::CREATE_TO);
        if (array_diff($monthsTo->toArray(true), $monthsFrom->toArray(true)) !== array_diff(
                $monthsFrom->toArray(true),
                $monthsTo->toArray(true)
            )
        ) {
            foreach ($monthsTo as $date) {
                $aggregation_keys[] = Aggregation::create(Month::create($event, $date));
            }
        }

        // Years
        $years = DatePeriod::createForYear($from, $to);
        foreach ($years as $date) {
            $aggregation_keys[] = Aggregation::create(Year::create($event, $date));
        }

        $arguments = array($this->hash($destKey), count($aggregation_keys));

        foreach ($aggregation_keys as $k) {
            array_push($arguments, $this->hash($k));
        }

        call_user_func_array(array($rc, 'zunionstore'), $arguments);

        $this->getRedisClient()->sadd($this->prefixTempKey . 'keys', $this->hash($destKey));
        $this->getRedisClient()->expire($this->hash($destKey), $this->expireTimeout);

        return $this;
    }


    /**
     * Returns the ids of an key or event
     *
     * @param  KeyInterface $key The key or the event
     * @param null $with_scores
     * @throws \Exception
     * @return array   The ids array
     */
    public function getIds(KeyInterface $key, $with_scores = null)
    {
        if ($key instanceof AggregationKeyInterface) {
            if (!$with_scores) {
                return $this->getRedisClient()->zrange($this->hash($key), 0, -1);
            }
            $result = array();
            foreach ($this->getRedisClient()->zrange($this->hash($key), 0, -1, 'WITHSCORES') as $member => $score) {
                $result[] = array($member,$score);
            }
            return $result;
        }

        $string = $this->getRedisClient()->get($this->hash($key));

        $data = $this->bitsetToString($string);

        $ids = array();
        while (false !== ($pos = strpos($data, '1'))) {
            $data[$pos] = 0;
            $ids[] = (int)($pos / 8) * 8 + abs(7 - ($pos % 8));
        }

        sort($ids);

        return $ids;

    }

    /**
     * Returns key's string representation
     * If $key is instance of UnitOfTimeInterface then default key prefix used,
     * otherwise temporary key prefix
     * @param KeyInterface $key
     * @return string
     */
    public function hash(KeyInterface $key)
    {
        if ($key instanceof UnitOfTimeInterface) {
            return $this->prefixKey . $key;
        }

        return $this->prefixTempKey . $key;

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
