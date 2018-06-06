<?php

namespace CanalTP\ScheduleBundle\Service;

use CanalTP\ScheduleBundle\Service\SchedulesService;
use CanalTP\ScheduleBundle\Entity\StopSchedules;
use CanalTP\TrafficBundle\Service\MediaDisruptionsService;
use CanalTP\ScheduleBundle\Service\Files\FileServiceInterface;

class StopSchedulesService extends SchedulesService
{
    protected $has_odt;
    protected $hour;
    protected $minute;
    protected $formatAdditionalData = array();

    private $trafficMediaDisruptionService;
    private $timetableService;

    /**
     * StopSchedulesService constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->setProcessorServiceId('navitia.stop_schedules.processor');
    }

    /**
     * @param MediaDisruptionsService $trafficMediaDisruptionService
     */
    public function setTrafficMediaDisruption(MediaDisruptionsService $trafficMediaDisruptionService)
    {
        $this->trafficMediaDisruptionService = $trafficMediaDisruptionService;
    }

    /**
     * @param FileServiceInterface $timetableService
     */
    public function setTimetable(FileServiceInterface $timetableService)
    {
        $this->timetableService = $timetableService;
    }

    /**
     * @return StopSchedules
     */
    public function generateRequest()
    {
        $region = $this->container->getParameter('navitia.region');
        $request = new StopSchedules();
        $request->setRegion($region);
        return $request;
    }

    public function callApi($entity, $configuration = null, $timeout = 5000, $pagination = true)
    {
        $raw = parent::callApi($entity, $configuration, $timeout, $pagination);
        return $this->processResult($raw, $entity);
    }

    /**
     * Function to process the result
     * if the lines is specified we process schedule board
     * else its the process for next departures
     * @param Object $raw
     * @param Object $entity
     * @return Object
     */
    private function processResult($raw, $entity)
    {
        $this->processDisruptions($raw);
        $result = $this->processFiles($raw);
        if (strpos($entity->getPathFilter(), '/lines/') !== false ||
            strpos($entity->getPathFilter(), '/stop_points/') !== false ||
            strpos($entity->getPathFilter(), '/stop_areas/') !== false) {
            $result = $this->processScheduleBoard($result);
        } else {
            $result = $this->processNextDepartures($result);
        }
        return $result;
    }

    /**
     * Function to get the global notes list
     * @param Object $notes
     * @return Array
     */
    public function getNotesList($notes)
    {
        $notesList = array();
        foreach ($notes as $note) {
            $notesList[$note->id] =  $note->value;
        }
        return $notesList;
    }

    /**
     * Function to add the additional data
     * @param Object $date
     * @param Int $num index of the last minute pushed
     * @return Array
     */
    public function formatAdditionnalData($date, $num)
    {
        $this->formatAdditionalData[$this->hour][$num] = array(
            'on_demand_transport' => false,
            'pick_up_only' => false,
            'drop_off_only' => false
        );
        if (isset($date->additional_informations)) {
            foreach ($date->additional_informations as $information) {
                $this->formatAdditionalData[$this->hour][$num][$information] = true;
            }
        }
        return $this->formatAdditionalData;
    }

    /**
     * Function to format the data
     * Datetime is rendered as a table containing minutes and the indexes is the hours
     * @param Array $formatDataArray
     * @return Array
     */
    public function formatData($formatDataArray)
    {
        if (array_key_exists($this->hour, $formatDataArray)) {
            array_push($formatDataArray[$this->hour], $this->minutes);
        } else {
            $formatDataArray[$this->hour] = array($this->minutes);
        }
        return $formatDataArray;
    }

    /**
     * Funtion to sort the date times for next departures
     * @param array $a
     * @param array $b
     */
    private function sortNextDatetime($a, $b)
    {
        if ($a->date_times[0]->date_time == $b->date_times[0]->date_time) {
            return 0;
        }
        return ($a->date_times[0]->date_time < $b->date_times[0]->date_time) ? -1 : 1;
    }

    /**
     * Function to group the schedule result by line
     * The goal is to have the next departures for the 10 first lines
     * @param object $schedule
     * @return array array of schedule grouped by line
     */
    private function groupScheduleByLine($schedule)
    {
        $schedule_by_line = array();
        foreach ($schedule as $stop_schedule) {
            if (!array_key_exists($stop_schedule->route->line->id, $schedule_by_line)) {
                $schedule_by_line[$stop_schedule->route->line->id] = array();
            }
            $stop_schedule->display_informations->network_id = $this->getTypeId(
                $stop_schedule->links,
                'network'
            );
            $schedule_by_line[$stop_schedule->route->line->id][] = $stop_schedule;
        }
        return $schedule_by_line;
    }

    /**
     * Function to get the type id using the links in navitia response
     * @param object $links links in navitia response
     * @param string $type (type must be network , route, line, commercial_mode, ...)
     * @return string id of the type
     */
    private function getTypeId($links, $type)
    {
        $id = null;
        foreach ($links as $link) {
            if ($link->type === $type) {
                $id = $link->id;
                break;
            }
        }
        return $id;
    }

    /**
     * Function to get the two first date time per route
     * @param array $schedule_by_line schedule grouped by lines
     * @return array
     */
    private function getTwoFirstDateTime($schedule_by_line)
    {
        foreach ($schedule_by_line as $schedule) {
            foreach ($schedule as $route) {
                if (count($route->date_times) > 2) {
                    array_splice($route->date_times, 2);
                }
            }
        }
        return $schedule_by_line;
    }

    /**
     * Function to delete the empty schedule
     * The goal is to delete the schedules where date times is empty
     * @param object $stop_schedules
     * @return object
     */
    private function deleteEmptySchedule($stop_schedules)
    {
        foreach ($stop_schedules as $key => $stop_schedule) {
            if (!isset($stop_schedule->date_times) || count($stop_schedule->date_times) <= 0) {
                unset($stop_schedules[$key]);
            }
        }
        return $stop_schedules;
    }

    /**
     * Function to process the next departures
     * @param object $raw
     * @return object
     */
    public function processNextDepartures($raw)
    {
        $rawResult = $raw->getResult();
        $config = $this->container->getParameter('schedule.form');
        if (isset($config['next_departures']['nb_next_departures'])) {
            $count = $config['next_departures']['nb_next_departures'];
        } else {
            $count =  10;
        }
        if (isset($rawResult->stop_schedules)) {
            // Delete the stop_schedules when date_times is empty
            $stopSchedules = $this->deleteEmptySchedule($rawResult->stop_schedules);
            // Sort the stop_schedules by date_times
            usort($stopSchedules, array($this, 'sortNextDatetime'));
            // group by line
            $scheduleByLine = $this->groupScheduleByLine($stopSchedules);
            // Get the x lines
            array_splice($scheduleByLine, $count);
            // retrieves the 2 first datetimes for each route
            $rawResult->stop_schedules = $this->removeDuplicates($this->getTwoFirstDateTime($scheduleByLine));
            // process disruptions
            $rawResult->stop_schedules = $this->processDeparturesDisruptions($rawResult->stop_schedules);
            $raw->setRaw($rawResult);
        }
        return $raw;
    }

    /**
     * Function to process the result for the schedule boards
     * @param object $raw stop schedule result
     */
    public function processScheduleBoard($raw)
    {
        $rawResult = $raw->getResult();
        if (isset($rawResult->stop_schedules)) {
            $notesList = $this->getNotesList($rawResult->notes);
            $stop_schedules = $rawResult->stop_schedules;
            $config = $this->container->getParameter('schedule.result');
            foreach ($stop_schedules as $ind => $stop) {
                $formatData = array();
                $timeSlotArray = array();
                $notesValueList = array();
                $notesKeyList = array();
                $this->has_odt = false;
                if (isset($stop->date_times)) {
                    foreach ($stop->date_times as $date) {
                        $lastPushIndex = 0;
                        if (isset($date->date_time)) {
                            $newdate = new \DateTime($date->date_time);
                            $this->hour  =  $newdate->format('H');
                            $this->minutes = $newdate->format('i');
                            $formatData = $this->formatData($formatData);
                            // index of the last minute pushed
                            $lastPushIndex = count($formatData[$this->hour]) - 1;
                            $timeSlotArray = $this->fillTimeSlots(
                                $formatData,
                                $timeSlotArray,
                                $config
                            );
                            $this->formatAdditionnalData($date, $lastPushIndex);
                            if (isset($date->links)) {
                                $this->formatAdditionalData[$this->hour][$lastPushIndex]['notes'] =
                                    $this->getArrayNotes(
                                        $date->links,
                                        $notesKeyList,
                                        $notesValueList,
                                        $notesList
                                    );
                            }
                        }
                    }
                    /* http://jira.canaltp.fr/browse/SQWAL-785 */
                    $nextTime = $this->getNextTimeSchedule($formatData, $config);
                }
                $stop_schedules[$ind]->has_odt = $this->has_odt;
                $stop_schedules[$ind]->notes = $notesValueList;
                $stop_schedules[$ind]->formatted_data = $formatData;
                $stop_schedules[$ind]->formatted_next_time = $nextTime;
                $stop_schedules[$ind]->formatted_time_slots = $timeSlotArray;
                $stop_schedules[$ind]->formatted_additional_data = $this->formatAdditionalData;
                $stop_schedules[$ind]->alert_datas = $this->renderAlertDatas($stop);
            }
            $rawResult->stop_schedules = $stop_schedules;
            $raw->setRaw($rawResult);
        }
        return $raw;
    }

    /**
     * Function to have the time slots for the stop's schedule
     * @param array $data formatted data
     * @param string $hour hour
     * @param array $timeSlotArray table containing the time slots and the corresponding time
     * @param array $config table of configuration
     * @return array timeSlots table
     */
    public function fillTimeSlots($data, $timeSlotArray, $config)
    {
        if (isset($config['time_slots']) && $config['time_slots']['active'] === true) {
            foreach ($config['time_slots']['slots'] as $slot) {
                if (!array_key_exists($slot['title'], $timeSlotArray)) {
                    $timeSlotArray[$slot['title']] = array();
                }
                // pass-date == true is only for evening timeslot
                if (isset($slot['pass-date']) && $slot['pass-date'] === true) {
                    // no more distinction between evening and early the next day
                    if ((int)$this->hour >= $slot['slot'][0] || (int)$this->hour < $slot['slot'][1]) {
                        $timeSlotArray[$slot['title']][$this->hour] = $data[$this->hour];
                    }
                } else {
                    if ((int)$this->hour >= $slot['slot'][0]
                        && (int)$this->hour <  $slot['slot'][1]) {
                        $timeSlotArray[$slot['title']][$this->hour] = $data[$this->hour];
                    }
                }
            }
        }
        return $timeSlotArray;
    }

    /**
     * Function to get the next departure in terms of timeslot
     * @param array $data formatted data
     * @param array $config table of configuration
     * @return array timeslot of NextDeparture
     */
    public function getNextTimeSchedule($data, $config)
    {
        $return = array();
        $schedule_form_params = $this->container->get('request')->query->get('schedule');
        if (isset($config['nbNextTime'])) {
            $nbNextSchedule = intval($config['nbNextTime'], 10);
        } else {
            $nbNextSchedule = 2;
        }
        // get minute and hour from the schedule form
        if (isset($schedule_form_params['from_datetime']['time']['hour']) &&
            isset($schedule_form_params['from_datetime']['time']['minute'])) {
            $nowHourFormated = $schedule_form_params['from_datetime']['time']['hour'];
            $nowMinuteFormated = $schedule_form_params['from_datetime']['time']['minute'];
        } else {
            $timezone = $this->container->getParameter('timezone');
            $now = new \DateTime('now', new \DateTimeZone($timezone));
            $nowHourFormated = $now->format('H');
            $nowMinuteFormated = $now->format('i');
        }
        foreach ($data as $hour => $minutes) {
            if ($hour >= $nowHourFormated) {
                foreach ($minutes as $key => $minute) {
                    if ((($minute >= $nowMinuteFormated) || ($hour > $nowHourFormated)) &&
                        ($nbNextSchedule > 0)) {
                        if (!isset($return[$hour])) {
                            $return[$hour] = array();
                        }
                        $return[$hour][$key] = $minute;
                        $nbNextSchedule = $nbNextSchedule - 1;
                    }
                }
            }
            if ($nbNextSchedule == 0) {
                break;
            }
        }
        return $return;
    }
    /**
     * Function to retrieves alert datas
     * @param object $route
     * @return array
     */
    public function renderAlertDatas($stop)
    {
        $lineName = $stop->display_informations->label;
        if ($stop->display_informations->code) {
            if ($stop->display_informations->label == $stop->display_informations->code) {
                $lineName = $stop->display_informations->code;
            } else {
                $lineName = $stop->display_informations->code . ' ' . $lineName;
            }
        }
        return array(
            'networks' => array(
                array(
                    'name' => $stop->display_informations->network,
                    'uri' => ''
                )
            ),
            'lines' => array(
                array(
                    'name' => $lineName,
                    'direction' => $stop->display_informations->direction,
                    'color' => $stop->display_informations->color,
                    'mode' => $stop->display_informations->commercial_mode,
                    'uri' => ''
                )
            )
        );
    }

    /**
     * Function to process disruptions
     * @param object $raw
     * @return object
     */
    private function processDisruptions($raw)
    {
        $rawResult = $raw->getResult();
        if (isset($rawResult->stop_schedules)) {
            foreach ($rawResult->stop_schedules as $stop) {
                $parts = [$stop->display_informations, $stop->stop_point, $stop->stop_point->stop_area];
                foreach ($parts as $part) {
                    if (isset($part->disruptions)) {
                        $part->disruptions = $this->trafficMediaDisruptionService->processMediaDisruptions(
                            $part->disruptions
                        );
                    }
                }
            }
        }
        return $raw->setRaw($rawResult);
    }

    /**
     * Function to process next departures disruptions
     * @param array $departures
     * @return array
     */
    private function processDeparturesDisruptions($departures)
    {
        foreach ($departures as $route) {
            $maxDisruptions = [];
            foreach ($route as $departure) {
                $parts = array(
                    'line' => $departure->display_informations,
                    'stop' => $departure->stop_point->stop_area
                );
                foreach ($parts as $type => $part) {
                    if (isset($part->disruptions)) {
                        $part->disruptions = $this->trafficMediaDisruptionService->processMediaDisruptions(
                            $part->disruptions
                        );
                        $maxDisruptions[] = $part->disruptions[0];
                    }
                }
                if (count($maxDisruptions) > 0) {
                    $maxDisruptions = $this->trafficMediaDisruptionService->processMediaDisruptions(
                        $maxDisruptions
                    );
                    $departure->display_disruptions = array(
                        'max_level' => $maxDisruptions[0]->severity->level,
                        'type' => $type
                    );
                }
            }
        }
        return $departures;
    }
    
    /**
     * Function to process files
     * @param object $raw
     * @return object
     */
    private function processFiles($raw)
    {
        $rawResult = $raw->getResult();
        if (isset($rawResult->stop_schedules)) {
            foreach ($rawResult->stop_schedules as $stopSchedule) {
                if (!empty($stopSchedule->stop_point->id)
                        && !empty($stopSchedule->route->id)
                        && !empty($stopSchedule->route->line->network->id)) {
                    if (!isset($stopSchedule->files)) {
                        $stopSchedule->files = new \stdClass();
                    }
                    $params = array(
                        'network' => $stopSchedule->route->line->network->id,
                        'route' => $stopSchedule->route->id,
                        'stopPoint' => $stopSchedule->stop_point->id
                    );
                    $stopSchedule->files->timetable = $this->timetableService->getLink($params);
                }
            }
        }

        return $raw->setRaw($rawResult);
    }

    private function removeDuplicates(array $stopSchedules)
    {
        $lineData = array();
        $routeIdentifiers = array();
        foreach ($stopSchedules as $lineId => $line) {
            foreach ($line as $routeData) {
                $routeIdentifier = $lineId . ';' . $routeData->route->id;
                if (!in_array($routeIdentifier, $routeIdentifiers)) {
                    $routeIdentifiers[] = $routeIdentifier;
                    $lineData[$lineId][] = $routeData;
                }
            }
        }
        return $lineData;
    }
}
