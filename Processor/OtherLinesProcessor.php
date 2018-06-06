<?php

namespace CanalTP\ScheduleBundle\Processor;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processor to recover other stop's departures
 * Call Netwoks then call lines
 */
class OtherLinesProcessor extends AbstractProcessor
{
    protected $networksApi;
    protected $linesApi;
    protected $has_excluded = false;
    private $config = array();

    /**
     * {@inheritdoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        parent::setContainer($container);
        $this->config = $container->getParameter('schedule.form');
    }

    /*
     * bindFromLines
     * Call networks to have the networks for this stop_area
     * Recovers the lines' information and call Navitia
     * @param coverageEntity $entity coverage entity
     */
    public function bindFromLines($entity)
    {
        $this->networksApi = $this->container->get('navitia.networks');
        $this->setApi($this->networksApi);
        $coverage = $this->api->generateRequest();
        $coverage->setPathFilter($entity->getPathFilter());
        $this->setEntity($coverage);
        $result = $this->call()->getResult();
        $filter = $entity->getPathFilter();

        // Récupération de la liste des réseaux exclus
        $otherLinesConfig = $this->config['other_lines'];
        $excludedNetworks = $this->container->get('excluded_networks_list_handler')->getList($otherLinesConfig);

        $result->excludeds_id = $excludedNetworks;
        if (!isset($result->error)) {
            foreach ($result->networks as $network) {
                if (!in_array($network->id, $excludedNetworks)) {
                    $lines = $this->getLines($excludedNetworks, $filter.'/networks/'.$network->id);
                    if (isset($lines->getResult()->lines)) {
                        $network->lines = $lines->getResult()->lines;
                    }
                } else {
                    $result->has_excluded = true;
                }
            }
        }

        return $result;
    }

    /**
     * Function to retrieve lines
     * @param string forbiddenId
     * @param string $networkFilter network filter
     * @return NavitiaRequestInterface
     */
    public function getLines($forbiddenId = null, $networkFilter = null)
    {
        $this->linesApi = $this->container->get('navitia.lines');
        $this->setApi($this->linesApi);
        $coverage = $this->api->generateRequest();
        if (!empty($networkFilter)) {
            $coverage->setPathFilter($networkFilter);
        }
        if ($forbiddenId !== null) {
            $coverage->setParameters(
                array(
                    'forbidden_id' => $forbiddenId
                )
            );
        }
        $this->setEntity($coverage);
        return $this->call();
    }

    /*
     * bindFromLinesFilter
     *
     * Recovers the lines' information and call Navitia using a filter
     * @param string $filter filter
     */
    public function bindFromLinesFilter($filter)
    {
        $this->networksApi = $this->container->get('navitia.networks');
        $this->setApi($this->networksApi);
        $coverage = $this->api->generateRequest();
        $coverage->setPathFilter($filter);
        return $this->bindFromLines($coverage);
    }
}
