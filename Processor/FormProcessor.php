<?php

namespace CanalTP\ScheduleBundle\Processor;

use CanalTP\ScheduleBundle\Entity\StopSchedules;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormTypeInterface;
use CanalTP\FrontCoreBundle\Form\DataTransformer\DatetimeToIsoTransformer;
use CanalTP\PlacesBundle\Autocomplete\AutocompleteConfigurationCollection;
use CanalTP\PlacesBundle\Autocomplete\AutocompleteConfiguration;
use CanalTP\PlacesBundle\Exception\AutocompleteValueChanged;
use CanalTP\PlacesBundle\Processor\AutocompleteProcessor;
use CanalTP\FrontCoreBundle\Form\Bridge\ErrorsBridge;

/**
 * Handles the schedule form (create, submit, validate, API calls)
 *
 * @property \CanalTP\ScheduleBundle\Entity\StopSchedules $entity
 * @property \CanalTP\ScheduleBundle\Service\RouteSchedulesService $api
 */
class FormProcessor extends AbstractProcessor
{
    protected $form;

    protected $formType;

    protected $formView;

    /**
     * @param FormTypeInterface $formType
     */
    public function setFormType(FormTypeInterface $formType)
    {
        $this->formType = $formType;
    }

    /**
     * Creates the schedule form (ScheduleType)
     *
     * @return \Symfony\Component\Form\Form|\Symfony\Component\Form\FormInterface
     */
    protected function createForm()
    {
        $this->entity = $this->api->generateRequest();
        $timezone = $this->container->getParameter('timezone');
        $datetime = new \DateTime('now', new \DateTimeZone($timezone));
        $iso = $datetime->format(DatetimeToIsoTransformer::ISO8601_BASE);
        $this->entity->setFromDatetime($this->roundUpMinuteForSelect($iso));
        $this->form = $this->container
            ->get('form.factory')
            ->create($this->formType, $this->entity);
        return $this->form;
    }

    /**
     * Returns the form view for this form (builded from $form->createView())
     *
     * @return \Symfony\Component\Form\Form|\Symfony\Component\Form\FormView
     */
    public function getFormView()
    {
        return $this->formView;
    }

    /**
     * Validates the request, calls navitia according to the requested data and binds errors to the form
     *
     * @param Request $request the request containing form data
     */
    public function processForm(Request $request)
    {
        $cleanForm = $this->createForm();
        $requestArray = $request->query->all();
        $formName = $cleanForm->getName();
        if (true == array_key_exists($formName, $requestArray)) {
            $configuration = $this->prepareAutocompleteDatas();
            try {
                $form = clone $cleanForm;
                $requestArray = $this->correctPrecisionRequest($configuration);
                $datas = $requestArray[key($requestArray)];
                $this->validateRequest($form, $datas, $configuration);
            } catch (AutocompleteValueChanged $e) {
                $form = clone $cleanForm;
                $this->validateRequest($form, $e->getDatas(), $configuration);
            }
            $responseManager = $this->getResponseManager();
            $responseManager->importResult($this->result);
            $result = $responseManager->getResult();
            if (!$result->hasErrors()) {
                $this->processStopPoints();
                $this->processLine();
                $this->processNextDepartures();
                $lineId = (isset($requestArray['line'])) ? $requestArray['line'] : null;
                $this->processSchedulesBoard($configuration, $lineId);
                $this->responseManagerAlert();
            }
            $this->formView = $form->createView();
            if ($result->hasErrors()) {
                $errors = $result->getErrors();
                $bridge = new ErrorsBridge();
                $this->formView = $bridge->attach($errors, $this->formView, $this->form);
            }
        } else {
            $this->formView = $cleanForm->createView();
        }
    }

    /**
     * Handle the printable result
     */
    public function processPrint()
    {
        $form = $this->createForm();
        $configuration = $this->prepareAutocompleteDatas();
        $requestArray = $this->correctPrecisionRequest($configuration);
        $datas = $requestArray[key($requestArray)];
        $this->validateRequest($form, $datas, $configuration);
        $responseManager = $this->getResponseManager();
        $responseManager->importResult($this->result);
        $lineId = (isset($requestArray['line'])) ? $requestArray['line'] : null;
        $routeId = (isset($requestArray['route'])) ? $requestArray['route'] : null;
        $this->processSchedulesBoard($configuration, $lineId, $routeId);
        $this->formView = $form->createView();
    }

    /**
     * This method modify the request to fill the value of autocomplete-hidden and autocomplete
     * parameters
     *
     * @param AutocompleteConfigurationCollection $configuration
     *
     * @return mixed
     */
    public function correctPrecisionRequest(AutocompleteConfigurationCollection $configuration)
    {
        $request = $this->container->get('request');
        $query = $request->query->all();
        foreach ($configuration as $fieldConfig) {
            $fieldName = $fieldConfig->getFieldName();
            if (isset($query[key($query)][$fieldName])) {
                $fieldQuery =& $query[key($query)][$fieldName];
                if (is_string($fieldQuery) && strpos($fieldQuery, '|') !== false) {
                    $value = array();
                    $parts = explode('|', $fieldQuery);
                    list($value['autocomplete-hidden'], $value['autocomplete']) = $parts;
                    $fieldQuery = $value;
                }
            }
        }
        $request->query->replace($query);

        return $query;
    }

    /**
     * @param $form
     * @param $datas
     * @param $configuration
     */
    public function validateRequest($form, $datas, $configuration)
    {
        $datas = $this->fillMissingTime($datas);
        $form->submit($datas);
        $this->api->processEntity($this->entity, $configuration);
        $violations = $this->api->validateEntity($this->entity, $configuration);
        if ($violations->count() > 0) {
            $this->api->findSolution($violations);
        }
        $this->form = $form;
        $this->result = $this->api->getProcessor()->getResponse();
    }

    private function fillMissingTime($datas)
    {
        $timezone = $this->container->getParameter('timezone');
        $return = $datas;
        if (is_array($datas) && array_key_exists('from_datetime', $datas)) {
            if (!array_key_exists('time', $datas['from_datetime'])) {
                $now = new \DateTime('now', new \DateTimeZone($timezone));
                $return['from_datetime']['time'] = array(
                    'hour' => $now->format('H'),
                    'minute' => $now->format('i')
                );
            }
        } else {
            // Default date if not present in URL
            $now = new \DateTime('now', new \DateTimeZone($timezone));
            $return['from_datetime'] = array(
                'date' => $now->format('d/m/Y'),
                'time' => array(
                    'hour' => $now->format('H'),
                    'minute' => $now->format('i')
                )
            );
        }

        return $return;
    }

    /**
     * Fonction permettant de gérer l'appel à lines
     * @return []
     */
    public function processLine()
    {
        $processor = $this->container->get('canaltp_schedule.other.lines.processor');
        $this->result = $processor->bindFromLines($this->entity);
        $responseManager = $this->getResponseManager();
        $responseManager->addResult('lines', $this->result);
        return $this->result;
    }

    public function prepareAutocompleteDatas()
    {
        $datas = new AutocompleteConfigurationCollection();
        $formParams = $this->container->getParameter('schedule.form');
        if (isset($formParams['autocomplete'])) {
            $source = $formParams['autocomplete'];
            foreach ($source as $field => $group) {
                $config = new AutocompleteConfiguration();
                $config
                    ->setFieldName($field)
                    ->setGroup($group);
                $datas->add($config);
            }
        }
        return $datas;
    }

    public function needPrecision($field = null)
    {
        $errors = $this->getMainResult()->getErrors();
        foreach ($errors as $fieldName => $fieldErrors) {
            foreach ($fieldErrors as $fieldError) {
                if (preg_match('/'.AutocompleteProcessor::$TOO_MANY_SOLUTIONS.'$/', $fieldError)) {
                    if (!$field || $field === $fieldName) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    public function getLineId($lineId = null)
    {
        if ($lineId === null) {
            $mainResult = $this->getMainResult()->getResult();
            if (isset($mainResult['next_stop_schedules'])) {
                if (is_object($mainResult['next_stop_schedules'])) {
                    if (isset($mainResult['next_stop_schedules']->stop_schedules)) {
                        if (count($mainResult['next_stop_schedules']->stop_schedules) > 0) {
                            $lineId = key($mainResult['next_stop_schedules']->stop_schedules);
                        }
                    }
                }
            }
        }

        return $lineId;
    }

    /**
     * Performs the call to /stop_schedules to display the next departures
     * As we want realtime data (if there is any available) we perform the request using
     * data_freshness to 'realtime'
     *
     * @return []
     */
    public function processNextDepartures()
    {
        $entity = clone $this->entity;
        $entity->setDataFreshness(StopSchedules::DATA_FRESHNESS_REALTIME);
        $processor = $this->container->get('canaltp_schedule.stop.schedule.processor');
        $this->result = $processor->bindFromStopSchedules($entity, true);
        $responseManager = $this->getResponseManager();
        $responseManager->addResult('next_stop_schedules', $this->result);
        return $this->result;
    }

    /**
     * Performs the call to /stop_schedules to display the schedule board
     * As we want theorical data this call only requests base schedules from navitia
     *
     * @param AutocompleteConfigurationCollection $configuration
     * @param string $lineId
     * @param string $routeId
     *
     * @return array|false
     */
    public function processSchedulesBoard($configuration, $lineId = null, $routeId = null)
    {
        $entity = clone $this->entity;
        $entity->setDataFreshness(StopSchedules::DATA_FRESHNESS_BASE_SCHEDULE);
        $todayDatetime = new \DateTime('today');
        $datetime = new \DateTime($this->entity->getFromDatetime());
        $datetime->setTime(4, 0, 0);

        if ($datetime < $todayDatetime) {
            $datetime = $todayDatetime;
        }

        $iso = $datetime->format(DatetimeToIsoTransformer::ISO8601_BASE);
        $entity->setFromDatetime($iso);
        $lineId = $this->getLineId($lineId);
        if ($lineId != null || $routeId != null) {
            $filter = $entity->getPathFilter();
            if ($lineId != null) {
                $filter = $filter.'/lines/'.$lineId;
            }
            if ($routeId != null) {
                $filter = $filter.'/routes/'.$routeId;
            }
            $entity->setPathFilter($filter);
            $this->result = $this->api->callApi($entity, $configuration);
            $responseManager = $this->getResponseManager();
            $responseManager->addResult('main', $this->result);
            return $this->result;
        }
        return false;
    }

    /**
     * Fonction permettant de gérer l'appel à stop_points
     *
     * @return array
     */
    public function processStopPoints()
    {
        $processor = $this->container->get('canaltp_schedule.stop.points.processor');
        $this->result = $processor->bindFromStopPoints($this->entity);
        $responseManager = $this->getResponseManager();
        $responseManager->addResult('stop_points', $this->result);
        return $this->result;
    }

    /**
     * Rounds up a time to the next 5 multiple (3->5, 16->20 ...)
     *
     * @param null $iso
     *
     * @return null|string
     */
    private function roundUpMinuteForSelect($iso = null)
    {
        if ($iso == null) {
            $iso = $this->entity->getFromDatetime();
        }

        $timezone = $this->container->getParameter('timezone');
        $transformer = new DatetimeToIsoTransformer($timezone);
        $datetime = $transformer->transform($iso);
        $remainder = $datetime->format('i') % 5;
        $add = (5 - $remainder);
        // modifies the datetime to add the minutes needed to reach the next 5 multiple
        $datetime->modify("+ ".$add." minutes");
        $iso = $transformer->reverseTransform($datetime);
        return $iso;
    }

    public function getAutocompleteData($field)
    {
        $request = $this->container->get('request');
        $query = $request->query->all();
        $fieldQuery = $query[key($query)][$field];
        $label = (isset($fieldQuery['autocomplete'])) ? $fieldQuery['autocomplete'] : '';
        $uri = (isset($fieldQuery['autocomplete-hidden'])) ? $fieldQuery['autocomplete-hidden'] : '';

        return array(
            'autocomplete' => $label,
            'autocomplete-hidden' => $uri
        );
    }

    /**
     * Adds uris to alert object
     */
    public function responseManagerAlert()
    {
        $result = $this->result->getResult();
        if (isset($result->stop_schedules)) {
            foreach ($result->stop_schedules as $stop_schedule) {
                if (isset($stop_schedule->alert_datas)) {
                    $stop_schedule->alert_datas['networks'][0]['uri'] = $stop_schedule->route->line->network->id;
                    $stop_schedule->alert_datas['lines'][0]['uri'] = $stop_schedule->route->line->id;
                }
            }
        }
    }
}
