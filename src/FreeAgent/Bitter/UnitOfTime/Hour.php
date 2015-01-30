<?php

namespace FreeAgent\Bitter\UnitOfTime;

/**
 * @author Jérémy Romey <jeremy@free-agent.fr>
 */
class Hour extends AbstractUnitOfTime
{
    public function getDateTimeFormated()
    {
        return sprintf(
            '%s-%s-%s-%s',
            $this->getDateTime()->format('Y'),
            $this->getDateTime()->format('m'),
            $this->getDateTime()->format('d'),
            $this->getDateTime()->format('H')
        );
    }

    /** @return \DateInterval */
    public function getInterval()
    {
        return new \DateInterval('P1H');
    }
}
