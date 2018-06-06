<?php

namespace CanalTP\ScheduleBundle\Processor;

class StopScheduleProcessor extends AbstractProcessor
{
    /*
     * bindFromStopSchedules
     *
     * Récupére les information de Stop Schedules puis fait l'appel Navitia
     * @param StopSchedules $entity entité de DepartureBoards
     */
    public function bindFromStopSchedules($entity, $addConfig)
    {
        if ($addConfig === true) {
            $this->prepareConfigParams($entity);
        }
        $this->setEntity($entity);
        return $this->api->callApi($entity);
    }

    /**
     * @param $entity
     * @return mixed
     */
    private function prepareConfigParams($entity)
    {
        $config = $this->container->getParameter('schedule.form');
        if (isset($config['next_departures']['nb_stoptimes'])
            && is_int($config['next_departures']['nb_stoptimes'])) {
            $entity->setCount($config['next_departures']['nb_stoptimes']);
        }

        // Récupération de la liste des réseaux exclus
        $nextDeparturesConfig = $config['next_departures'];
        $excludedNetworks = $this->container->get('excluded_networks_list_handler')->getList($nextDeparturesConfig);
        $entity->setForbiddenId($excludedNetworks);

        return $entity;
    }
}
