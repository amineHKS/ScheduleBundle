<?php

namespace CanalTP\ScheduleBundle\Service;

use CanalTP\ScheduleBundle\Service\SchedulesService;
use CanalTP\TrafficBundle\Service\MediaDisruptionsService;
use CanalTP\ScheduleBundle\Entity\RouteSchedules;

class RouteSchedulesService extends SchedulesService
{
    private $trafficMediaDisruptionService;

    /**
     * RouteSchedulesService constructor.
     * @param MediaDisruptionsService $trafficMediaDisruptionService
     */
    public function __construct(MediaDisruptionsService $trafficMediaDisruptionService)
    {
        parent::__construct();
        $this->setProcessorServiceId('navitia.route_schedules.processor');
        $this->trafficMediaDisruptionService = $trafficMediaDisruptionService;
    }

    /**
     * @return RouteSchedules
     */
    public function generateRequest()
    {
        $region = $this->container->getParameter('navitia.region');
        $request = new RouteSchedules();
        $request->setRegion($region);
        return $request;
    }

    /**
     * @param $entity
     * @param null $configuration
     * @param int $timeout
     * @param bool $pagination
     * @return mixed
     */
    public function callApi($entity, $configuration = null, $timeout = 15000, $pagination = true)
    {
        $raw = parent::callApi($entity, $configuration, $timeout, $pagination);
        return $this->formatData($raw);
    }

    /**
     * @TODO prise en compte des différents headers
     *
     * @param $raw
     * @return mixed
     */
    public function formatData($raw)
    {
        $rawResult = $raw->getResult();
        if (isset($rawResult->route_schedules)) {
            $notesList = array();
            foreach ($rawResult->notes as $note) {
                $notesList[$note->id] =  $note->value;
            }
            $routeSchedules = $this->processDisruptions($rawResult->route_schedules);
            foreach ($routeSchedules as $ind => $route) {
                $formatCell = array();
                $notesValueList = array();
                $notesKeyList = array();
                $has_odt = false;
                $routeSchedules[$ind]->has_stop_disruptions = false;
                if (isset($route->table->headers)) {
                    foreach ($route->table->headers as $header) {
                        if (isset($header->additional_informations)) {
                            $additional_infos = $header->additional_informations;
                            if (in_array('odt_with_zone', $additional_infos)) {
                                $has_odt = true;
                                break;
                            }
                            if (in_array('odt_with_stop_time', $additional_infos)) {
                                $has_odt = true;
                                break;
                            }
                        }
                    }
                }
                $routeSchedules[$ind]->has_odt = $has_odt;
                foreach ($route->table->rows as $key => $row) {
                    if (isset($row->date_times)) {
                        foreach ($row->date_times as $colId => $date) {
                            if (isset($date->links)) {
                                $notes = $this->getArrayNotes(
                                    $date->links,
                                    $notesKeyList,
                                    $notesValueList,
                                    $notesList
                                );
                                $formatCell[$key][$colId]['notes'] = $notes;
                            }
                        }
                    }
                    // Add city name for display
                    $name = $row->stop_point->name;
                    // Process stop disruptions
                    $row = $this->processStopDisruptions($routeSchedules[$ind], $row);
                    if (isset($row->stop_point->administrative_regions[0]->name) &&
                        ($row->stop_point->administrative_regions[0]->name !== "")) {
                        $name = $name .
                            ', ' .
                            $row->stop_point->administrative_regions[0]->name;
                    }
                    $routeSchedules[$ind]->table->rows[$key]->name = $name;
                }
                $routeSchedules[$ind]->notes = $notesValueList;
                $routeSchedules[$ind]->formatted_cell = $formatCell;
                $routeSchedules[$ind]->alert_datas = $this->renderAlertDatas($route);
            }
            $rawResult->route_schedules = $routeSchedules;
            $raw->setRaw($rawResult);
        }
        return $raw;
    }

    /**
     * Function to retrieves alert datas
     * @param object $route
     * @return array
     */
    public function renderAlertDatas($route)
    {
        $lineName = $route->display_informations->label;
        if ($route->display_informations->code) {
            if ($route->display_informations->label == $route->display_informations->code) {
                $lineName = $route->display_informations->code;
            } else {
                $lineName = $route->display_informations->code . ' ' . $lineName;
            }
        }
        return array(
            'networks' => array(
                array(
                    'name' => $route->display_informations->network,
                    'uri' => ''
                )
            ),
            'lines' => array(
                array(
                    'name' => $lineName,
                    'direction' => $route->display_informations->direction,
                    'color' => $route->display_informations->color,
                    'mode' => $route->display_informations->commercial_mode,
                    'uri' => ''
                )
            )
        );
    }

    /**
     * Function to process disruptions
     * @param object $routeSchedules
     * @return object
     */
    private function processDisruptions($routeSchedules)
    {
        foreach ($routeSchedules as $route) {
            $display = $route->display_informations;
            if (isset($display->disruptions)) {
                $display->disruptions = $this->trafficMediaDisruptionService->processMediaDisruptions(
                    $display->disruptions
                );
            }
        }
        return $routeSchedules;
    }
    
    /**
     * Function to process stop disruptions
     * @param object $routeSchedule
     * @param object $row
     * @return object
     */
    private function processStopDisruptions($routeSchedule, $row)
    {
        $parts = [$row->stop_point->stop_area, $row->stop_point];
        foreach ($parts as $part) {
            if (isset($part->disruptions)) {
                $part->disruptions = $this->trafficMediaDisruptionService->processMediaDisruptions(
                    $part->disruptions
                );
                $routeSchedule->has_stop_disruptions = true;
                if (count($part->disruptions) > 0) {
                    $row->disruption_display = array(
                        'level' => $part->disruptions[0]->severity->level,
                        'message' => $part->disruptions[0]->messages[0]->text
                    );
                }
            }
        }
        return $row;
    }
}
