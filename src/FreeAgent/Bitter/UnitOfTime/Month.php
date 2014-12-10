<?php

namespace FreeAgent\Bitter\UnitOfTime;

/**
 * @author Jérémy Romey <jeremy@free-agent.fr>
 */
class Month extends AbstractUnitOfTime
{
    public function getDateTimeFormated()
    {
        return sprintf('%s-%s', $this->getDateTime()->format('Y'), $this->getDateTime()->format('m'));
    }
}
