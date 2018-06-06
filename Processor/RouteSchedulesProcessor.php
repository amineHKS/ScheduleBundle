<?php

namespace CanalTP\ScheduleBundle\Processor;

use CanalTP\PlacesBundle\Processor\CoverageProcessor;

class RouteSchedulesProcessor extends CoverageProcessor
{
    /**
     * {@inheritDoc}
     * Cette surcharge intervient car ici on ignore le partie temps du DateTime
     */
    protected function adjustDateTimeFields()
    {
        $this->setAdjustTime(false);
        parent::adjustDateTimeFields();
    }
}
