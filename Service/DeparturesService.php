<?php

namespace CanalTP\ScheduleBundle\Service;

use CanalTP\ScheduleBundle\Service\SchedulesService;
use CanalTP\ScheduleBundle\Entity\Departures;

class DeparturesService extends SchedulesService
{
    /**
     * @return Departures
     */
    public function generateRequest()
    {
        $region = $this->container->getParameter('navitia.region');
        $request = new Departures();
        $request->setRegion($region);
        return $request;
    }
}
