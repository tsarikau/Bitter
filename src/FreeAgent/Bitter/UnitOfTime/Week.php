<?php

namespace FreeAgent\Bitter\UnitOfTime;

/**
 * @author Jérémy Romey <jeremy@free-agent.fr>
 */
class Week extends AbstractUnitOfTime
{
    public function getDateTimeFormated()
    {
        return sprintf('%s-W%s', $this->getDateTime()->format('Y'), $this->getDateTime()->format('W'));
    }

    /** @return \DateInterval */
    public function getInterval()
    {
        return new \DateInterval('P1W');
    }
}
