<?php

namespace CanalTP\ScheduleBundle\Processor;

use CanalTP\FrontCoreBundle\Form\DataTransformer\DatetimeToIsoTransformer;

/**
 * Processor to retrieve the stop_point's start times
 */
class NextDepartureMapProcessor extends AbstractProcessor
{
    protected $stopScheduleApi;

    /*
     * bindFromStopScheduleFilter
     *
     * Call stop_schedule with the Filter as parameter
     * @param string $filter Filter
     */
    public function bindFromStopScheduleFilter($filter)
    {
        $this->stopScheduleApi = $this->container->get('navitia.stop_schedules');
        $this->setApi($this->stopScheduleApi);
        $coverage = $this->api->generateRequest();
        $coverage->setPathFilter($filter);
        $coverage->setFromDatetime($this->getDateTime());
        $this->setEntity($coverage);
        return $this->call()->getResult();
    }

    /**
     * Function to retrieve the current time
     * @return DateTime
     */
    public function getDateTime()
    {
        $datetime = new \DateTime();
        $iso = $datetime->format(DatetimeToIsoTransformer::ISO8601_BASE);
        return $this->roundUpMinuteForSelect($iso);
    }

    /*
     * roundUpMinuteForSelect
     *
     * Rounding the minutes of a date in ISO format to a multiple of 5
     *
     * @param String $iso Datetime in ISO format
     *
     * @return String
     */
    private function roundUpMinuteForSelect($iso)
    {
        $timezone = $this->container->getParameter('timezone');
        $transformer = new DatetimeToIsoTransformer($timezone);
        $datetime = $transformer->transform($iso);
        $remainder = $datetime->format('i') % 5;
        $add = (5 - $remainder);
        $datetime->modify("+ ".$add." minutes");
        return $transformer->reverseTransform($datetime);
    }
}
