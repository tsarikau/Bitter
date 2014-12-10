<?php

namespace FreeAgent\Bitter\UnitOfTime;

/**
 * @author Jérémy Romey <jeremy@free-agent.fr>
 */
class Year extends AbstractUnitOfTime
{
    public function getDateTimeFormated()
    {
        return sprintf('%s', $this->getDateTime()->format('Y'));
    }
}
