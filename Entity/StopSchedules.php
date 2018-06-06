<?php

namespace CanalTP\ScheduleBundle\Entity;

use Navitia\Component\Request\Parameters\CoverageStopSchedulesParameters;
use CanalTP\PlacesBundle\Entity\CoverageEntityInterface;
use Symfony\Component\Validator\Constraints as Assert;
use CanalTP\ScheduleBundle\Validator\Constraints as CtpAssert;

class StopSchedules extends CoverageStopSchedulesParameters implements CoverageEntityInterface
{
    /**
     * @Assert\NotBlank(message="stop_schedules.stop_area.errors.blank")
     */
    private $stop_area;
    private $path_filter;
    /**
     * @CtpAssert\DateRange
     */
    protected $from_datetime;
    private $action;
    private $region;
    private $option;
    protected $disable_geojson = true;

    public function __construct()
    {
        $this->setAction('stop_schedules');
    }

    public function getPathFilter()
    {
        return $this->path_filter;
    }

    public function setPathFilter($path_filter)
    {
        $this->path_filter = $path_filter;
        $this->stop_area = $path_filter;
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

    public function getOption()
    {
        return $this->option;
    }

    public function setOption($option)
    {
        $this->option = $option;
    }

    public function getDisableGeojson()
    {
        return $this->disable_geojson;
    }

    public function setDisableGeojson($disableGeojson)
    {
        $this->disable_geojson = $disableGeojson;
    }
}
