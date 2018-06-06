<?php

namespace CanalTP\ScheduleBundle\Entity;

use Navitia\Component\Request\Parameters\CoverageDeparturesParameters;
use CanalTP\PlacesBundle\Entity\CoverageEntityInterface;
use CanalTP\ScheduleBundle\Validator\Constraints as CtpAssert;

class Departures extends CoverageDeparturesParameters implements CoverageEntityInterface
{
    private $path_filter;
    /**
     * @CtpAssert\DateRange
     */
    protected $from_datetime;
    private $action;
    private $region;

    public function __construct()
    {
        $this->setAction('departures');
    }

    public function getPathFilter()
    {
        return $this->path_filter;
    }

    public function setPathFilter($path_filter)
    {
        $this->path_filter = $path_filter;
        return $this;
    }

    public function getRegion()
    {
        return $this->region;
    }

    public function setRegion($region)
    {
        $this->region = $region;
        return $this;
    }

    public function getAction()
    {
        return $this->action;
    }

    public function setAction($action)
    {
        $this->action = $action;
        return $this;
    }

    public function getApiName()
    {
        return 'coverage';
    }
}
