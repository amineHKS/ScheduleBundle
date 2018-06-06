<?php

namespace CanalTP\ScheduleBundle\Processor;

class StopPointsProcessor extends AbstractProcessor
{
    /*
     * bindFromStopPoints
     *
     * Fait l'appel Navitia pour récupérer les StopPoints
     * @param StopPoints $dBEntity entité de StopPoints
     */
    public function bindFromStopPoints($dBEntity)
    {
        $coverage = $this->api->generateRequest();
        $coverage->setPathFilter($dBEntity->getPathFilter());
        $this->setEntity($coverage);
        return $this->call();
    }
}
