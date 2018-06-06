<?php

namespace CanalTP\ScheduleBundle\Controller;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class MapController implements ContainerAwareInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * {@inheritDoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function popupAction()
    {
        $request = $this->container->get('request');
        $params = array();
        if ($request->getMethod() == 'POST') {
            $post = $request->request->all();
            foreach ($post as $key => $value) {
                $params[$key] = $value;
            }
            if (array_key_exists('stopPointId', $params)) {
                //Navitia call to manage the display of lines on a StopPoint
                $params['result_lines'] = $this->getOtherLines(
                    'stop_points/' . $params['stopPointId'] . '/'
                );
                $params['result_NDepartures'] = $this->getNextDepartures(
                    'stop_points/' . $params['stopPointId'] . '/'
                );
            }
            $fromDatetime = $this->getFromDateTime($params);
            $params['stopAreaName'] = (isset($params['stopAreaName'])) ? $params['stopAreaName'] : $params['name'];
            $params['stopAreaId'] = (isset($params['stopAreaId'])) ? $params['stopAreaId'] : $params['id'];
            $params['urlParams'] = $this->createLink(
                $params['stopAreaName'],
                $params['stopAreaId'],
                $fromDatetime
            );
        }
        return $this->container->get('templating')->renderResponse(
            'CanalTPScheduleBundle:Map:popup.html.twig',
            $params
        );
    }

    /**
     * Navitia call to retrieve the other lines on a StopPoint
     * @param string $filter
     * @return array
     */
    public function getOtherLines($filter)
    {
        $processor = $this->container->get('canaltp_schedule.other.lines.processor');
        return $processor->bindFromLinesFilter($filter);
    }
    /**
     * Function use to render the other lines template for other bundle
     * @param string $filter filter to call navitia
     * @param string $name stop's name
     * @param string $id stop's id
     * @param boolean $display_title boolean to display or not the title
     * @param boolean $accordion accordion display
     * @param boolean $accordion_open opened or closed accordion
     */
    public function renderOtherLinesAction(
        $filter,
        $name,
        $id,
        $display_title = true,
        $accordion = true,
        $accordion_open = false
    ) {
        $params = array(
            'result_lines' => $this->getOtherLines($filter),
            'display_title' => $display_title,
            'urlParams' => $this->createLink($name, $id),
            'stop_area_name' => $name,
            'stop_area_id' => $id,
            'accordion' => $accordion,
            'accordion_open' => $accordion_open
        );
        return $this->container->get('templating')->renderResponse(
            'CanalTPScheduleBundle:Schedule:other_lines.html.twig',
            $params
        );
    }
    /**
     * Navitia call to retrieve the next departures on a StopPoint
     * @param string $filter
     * @return array
     */
    public function getNextDepartures($filter)
    {
        $processor = $this->container->get('canaltp_schedule.next.departure.processor');
        return $processor->bindFromStopScheduleFilter($filter);
    }

    /**
     * Function use to render the next schedule template for other bundle
     * @param string $filter the navitia filter
     * @param integer $limit the number of schedule per route
     * @param string $id stop_area id
     * @param boolean $accordion accordion display
     * @param boolean $accordion_open opened or closed accordion
     */
    public function renderNextDeparturesAction(
        $filter,
        $limit = null,
        $id = null,
        $accordion = true,
        $accordion_open = false
    ) {
        $result = $this->getNextDepartures($filter);
        $stop_schedules = array_values($result->stop_schedules);
        $name = !empty($stop_schedules) ? $stop_schedules[0][0]->stop_point->label : '';
        $params = array(
            'next_departures' => $result,
            'stop_point_name' => $name,
            'urlParams' => $this->createLink($name, $id),
            'nb_next_schedules' => ScheduleController::NEXT_SCHEDULE_ROWS,
            'accordion' => $accordion,
            'accordion_open' => $accordion_open
        );
        if ($limit !== null) {
            $params['limit'] = $limit;
        }
        return $this->container->get('templating')->renderResponse(
            'CanalTPScheduleBundle:Schedule:next_schedule.html.twig',
            $params
        );
    }

    /**
     * Function to create the schedule result link
     * @param string $name
     * @param string $id
     * @param array $fromDateTime
     * @return array
     */
    public function createLink($name, $id, $fromDateTime = null)
    {
        if ($fromDateTime === null) {
            $fromDateTime = array('date' => date("d/m/Y"));
        }
        $request = Request::create(
            '/schedule/result',
            'GET',
            array(
                'schedule' => array(
                    'stop_area' => array(
                        'autocomplete' => $name,
                        'autocomplete-hidden' => $id
                    ),
                    'from_datetime' => $fromDateTime
                )
            )
        );
        return $request->query->all();
    }
    
    /**
     * Function to get from_datetime parameter
     * @param array $params
     */
    private function getFromDateTime($params)
    {
        $fromDateTime = null;
        if (array_key_exists('urlParams', $params)) {
            foreach ($params as $param) {
                if ($param === 'from_datetime') {
                    $fromDateTime = $param;
                }
            }
        }
        return $fromDateTime;
    }
}
