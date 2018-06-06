<?php

namespace CanalTP\ScheduleBundle\Processor;

/**
 * Processor permettant de faire un appel coverage lines
 */
class CoverageLineProcessor extends AbstractProcessor
{
    protected $groupBy_config;
    /*
     * bindFromLines
     *
     * Récupére les information de lines puis fait l'appel Navitia
     * @param coverageEntity $entity entité de coverage
     */
    public function bindFromLines($entity)
    {
        $coverage = $this->api->generateRequest();
        $coverage->setPathFilter($entity->getPathFilter());
        $this->setEntity($coverage);
        $this->call();
        $this->getGroupedLines();
    }

    /**
     * Fonction permettant de gérer le regroupement les lignes
     * en fonction du paramètre groupBy (paramètre en config)
     * @return Object
     */
    public function getGroupedLines()
    {
        $config = $this->container->getParameter('schedule.form');
        $this->setGroupByConfig($config['coverage_lines']['groupBy']);
        if (isset($this->groupBy_config)) {
            return $this->groupLines();
        } else {
            return $this->result->getResult();
        }
    }

    /**
     * Fonction permettan de faire le regroupement
     * @return Object
     */
    public function groupLines()
    {
        $lines = array();
        $result = $this->result->getResult();
        $keys = explode('.', $this->groupBy_config);
        if (isset($result->lines)) {
            foreach ($result->lines as $line) {
                $groupKey = $line;
                foreach ($keys as $key) {
                    $groupKey = $groupKey->$key;
                }
                $lines[$groupKey][] = $line;
            }
            $result->lines = $lines;
        }
        return $result;
    }

    /**
     * Getter de la configuration de groupBy
     * @return String
     */
    public function getGroupByConfig()
    {
        return $this->groupBy_config;
    }

    /**
     * Setter de la configuration de groupBy
     * @param String $groupBy_config
     */
    public function setGroupByConfig($groupBy_config)
    {
        $this->groupBy_config = $groupBy_config;
    }
}
