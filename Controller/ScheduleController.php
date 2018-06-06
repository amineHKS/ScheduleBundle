<?php

namespace CanalTP\ScheduleBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Response;

class ScheduleController extends Controller implements ContainerAwareInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;
    const NEXT_SCHEDULE_ROWS = 3;

    /**
     * {@inheritDoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function indexAction(Request $request)
    {
        $navitiaAvailabilityService = $this->get('navitia.availability');
        $navitiaResponse = $navitiaAvailabilityService->callApi();
        $this->container->set('request', $request);
        return $this->render(
            'CanalTPScheduleBundle:Schedule:index.html.twig',
            array(
                'navitia_response' => $navitiaResponse
            )
        );
    }

    /**
     * Renders Js for the section module
     * @return Response
     */
    public function scheduleJsAction()
    {
        return $this->render(
            'CanalTPScheduleBundle:Form:search.js.twig',
            array(
                'js_config' => $this->container->getParameter('javascript.schedule'),
            )
        );
    }

    /**
     * Fonction permettant de gérer le starter horaire
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return type
     */
    public function scheduleAction(Request $request, $options = array())
    {
        $navitiaAvailabilityService = $this->get('navitia.availability');
        $navitiaResponse = $navitiaAvailabilityService->callApi();
        $this->container->set('request', $request);
        $processor = $this->get('canaltp_schedule.form.processor');
        $processor->getResponseManager()->reset();
        $processor->processForm($request);
        $renderVars = array(
            'form' => $processor->getFormView(),
            'js_config' => $this->container->getParameter('javascript.schedule'),
            'schedule_enabled_catch_message' => $this->container->getParameter('schedule.enabled_catch_message'),
        );
        $result = $processor->getMainResult();
        $renderVars['errors'] = $result->getErrors();
        $renderVars['infos'] = $result->getInformations();
        $renderVars['render_context'] = $request->get('render_context');
        $renderVars['navitia_response'] = $navitiaResponse;
        $renderVars['options'] = $this->manageOptions($request->query->all(), $options, 'schedule');
        return $this->render(
            'CanalTPScheduleBundle:Form:search.html.twig',
            $renderVars
        );
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function precisionAction(Request $request)
    {
        $navitiaAvailabilityService = $this->get('navitia.availability');
        $navitiaResponse = $navitiaAvailabilityService->callApi();
        $processor = $this->get('canaltp_schedule.form.processor');
        $processor->processForm($request);
        $result = $processor->getMainResult();
        $router = $this->get('router');
        if (!$processor->isMainResultReady()) {
            $response = new RedirectResponse(
                $router->generate('schedule_home', $request->query->all())
            );
        } else {
            $scheduleRequest = $request->query->all();
            unset($scheduleRequest['schedule']['stop_area']);
            $params = array(
                'form' => $processor->getFormView(),
                'js_config' => $this->container->getParameter('javascript.schedule'),
                'result' => $result->getResult(),
                'infos' => $result->getInformations(),
                'navitia_response' => $navitiaResponse,
                'redirect' => array(
                    'schedule' => $router->generate('schedule_home', $scheduleRequest)
                )
            );

            $precisionProcessor = $this->get('canaltp_schedule.precision.processor');
            $precisionProcessor->setEntity($processor->getEntity());

            if (!(isset($navitiaResponse->error))) {
                $precisionProcessor->processForm($request);
                $params['precisionForm'] = $precisionProcessor->getFormView();
            }

            $response = $this->render(
                'CanalTPScheduleBundle:Schedule:precision.html.twig',
                $params
            );
        }
        return $response;
    }

    /**
     * Renders the schedule request result
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return type
     */
    public function resultAction(Request $request)
    {
        $processor = $this->get('canaltp_schedule.form.processor');
        $processor->processForm($request);
        $result = $processor->getMainResult();
        $navitiaAvailabilityService = $this->get('navitia.availability');
        $navitiaResponse = $navitiaAvailabilityService->callApi();

        if (!$processor->isMainResultReady() || $result->hasErrors()) {
            $route = ($processor->needPrecision()) ? 'schedule_form_precision' : 'schedule_home';
            $response = new RedirectResponse(
                $this->get('router')->generate($route, $request->query->all())
            );
        } else {
            $raw = $result->getResult();
            $lineCount = 0;
            $resultLinesHaveSncf = false;
            if (isset($raw['lines']->networks)) {
                foreach ($raw['lines']->networks as $network) {
                    if (isset($network->lines)) {
                        $lineCount = $lineCount + count($network->lines);
                    }
                    if (isset($network->id) && $network->id == 'network:SNCF') {
                        $resultLinesHaveSncf = true;
                    }
                }
            }
            $formView = $processor->getFormView();
            $params = array(
                'form' => $formView,
                'js_config' => $this->container->getParameter('javascript.schedule'),
                'next_departures' => $raw['next_stop_schedules'],
                'result' => $raw['main'],
                'infos' => $result->getInformations(),
                'result_lines' => $raw['lines'],
                'result_lines_have_sncf' => $resultLinesHaveSncf,
                'result_stop_points' => $raw['stop_points'],
                'powered_by' => true,
                'line_count' => $lineCount,
                'nb_next_departures' => $this->container->getParameter('schedule_nb_next_departures'),
                'nb_next_schedules' => self::NEXT_SCHEDULE_ROWS,
                'social_issuer' => 'schedule',
                'navitia_response' => $navitiaResponse,
                'array_content_identifier' => $this->container->getParameter('journey.content_identifier'),
                'stop_area_name' => $formView->children['stop_area']['autocomplete']->vars['value'],
                'stop_area_id' => $formView->children['stop_area']->vars['data']
            );
            $response = $this->render(
                'CanalTPScheduleBundle:Schedule:result.html.twig',
                $params
            );
        }
        return $response;
    }

    /**
     * Function to manage the print version
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function printAction(Request $request)
    {
        $processor = $this->get('canaltp_schedule.form.processor');
        $processor->processPrint($request);
        $result = $processor->getMainResult();
        $raw = $result->getResult();
        $params = array(
            'form' => $processor->getFormView(),
            'result' => $raw['main'],
            'infos' => $result->getInformations(),
            'printable' => true
        );
        return $this->render(
            'CanalTPScheduleBundle:Schedule:result_print.html.twig',
            $params
        );
    }

    /**
     * Downloads the timetable version
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function timetableAction(Request $request)
    {
        $query = $request->query;
        $params = array(
            'network' => $query->get('network'),
            'route' => $query->get('route'),
            'stopPoint' => $query->get('stopPoint')
        );
        if ($request->get('filter')) {
            $params['filter'] = $query->get('filter');
        }

        $service = $this->get('canaltp.timetable');
        $link = $service->getLink($params);

        if (empty($link)) {
            throw new NotFoundHttpException('Timetable file not found.');
        }

        $response = new Response();
        $curlHandle = curl_init();

        curl_setopt($curlHandle, CURLOPT_URL, $link);
        curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 6);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);

        $content = curl_exec($curlHandle);
        $response->headers->set('Cache-Control', 'private');
        $response->headers->set('Content-type', 'application/pdf');
        $response->sendHeaders();
        $response->setContent($content);

        curl_close($curlHandle);

        return $response;
    }

    /**
     * Fonction permettant de gérer le starter de horaire de lignes
     */
    public function lineScheduleAction(Request $request)
    {
        $navitiaAvailabilityService = $this->get('navitia.availability');
        $navitiaResponse = $navitiaAvailabilityService->callApi();
        $processorLine = $this->get('canaltp_schedule.line.form.processor');
        $processorLine->getResponseManager()->reset();
        $processorLine->processForm($request);
        $ajaxType = $request->query->get('ajaxType');
        $renderVars = array(
            'form' => $processorLine->getFormView(),
            'navitia_response' => $navitiaResponse,
            'js_config' => $this->container->getParameter('javascript.schedule')
        );
        $result = $processorLine->getMainResult();
        if ($processorLine->isMainResultReady()) {
            $renderVars['errors'] = $result->getErrors();
        }
        if ($request->isXmlHttpRequest() && null == $ajaxType) {
            $response = $this->render(
                'CanalTPScheduleBundle:LineForm:line_search_form_fields.html.twig',
                $renderVars
            );
        } else {
            $response = $this->render(
                'CanalTPScheduleBundle:LineForm:line_search.html.twig',
                $renderVars
            );
        }
        return $response;
    }

    /**
     * Fonction permettant de gerer le resut de horaire de ligne
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function lineResultAction(Request $request)
    {
        $routeName = $this->get('request')->get('_route');
        $processorLine = $this->get('canaltp_schedule.line.form.processor');
        $processorLine->processForm($request);
        $result = $processorLine->getMainResult();
        $navitiaAvailabilityService = $this->get('navitia.availability');
        $navitiaResponse = $navitiaAvailabilityService->callApi();
        if (!$processorLine->isMainResultReady() || $result->hasErrors()) {
            $response = new RedirectResponse(
                $this->get('router')->generate('schedule_home', $request->query->all())
            );
        } else {
            $lineResult = $result->getResult();
            $form = $processorLine->getFormView();
            if ($routeName === 'line_schedule_result_print') {
                $config = $this->container->getParameter('schedule.result');
                $response = $this->render(
                    'CanalTPScheduleBundle:LineSchedule:line_result_print.html.twig',
                    array(
                        'result' => $lineResult,
                        'printable' => true,
                        'date' => $form->vars['value']->getFromDatetime(),
                        'print_nb_row' => $config['print_nb_row']
                    )
                );
            } else {
                $requestArray = $request->query->get('lineSchedule');
                $scheduleFormConfig = $this->container->getParameter('schedule.form');
                if (isset($requestArray['line_daypart'])) {
                    $lineDaypart = explode('-', $requestArray['line_daypart']);
                } else {
                    $lineDaypart = explode('-', $scheduleFormConfig['line_daypart'][0]);
                }

                $params = array(
                    'form' => $form,
                    'result' => $lineResult,
                    'current' => $request->query->get('lineSchedule'),
                    'routes' => $processorLine->getRoutes(),
                    'js_config' => $this->container->getParameter('javascript.schedule'),
                    'infos' => $result->getInformations(),
                    'powered_by' => true,
                    'social_issuer' => 'schedule',
                    'line_daypart' => $lineDaypart,
                    'array_content_identifier' => $this->container->getParameter('journey.content_identifier'),
                    'navitia_response' => $navitiaResponse,
                );
                $response = $this->render(
                    'CanalTPScheduleBundle:LineSchedule:line_result.html.twig',
                    $params
                );
            }
        }
        return $response;
    }

    /**
     * Fonction permettant de gerer la grille horaire en deux arrets
     */
    public function multimodalScheduleAction(Request $request, $options = array())
    {
        $showMultimodal = true;
        $this->container->set('request', $request);
        $config = $this->container->getParameter('schedule.form');
        $navitiaAvailabilityService = $this->get('navitia.availability');
        $navitiaResponse = $navitiaAvailabilityService->callApi();
        if (key_exists('multimodal', $config) && key_exists('active', $config['multimodal'])) {
            $showMultimodal = ($config['multimodal']['active'] == 'true') ? true : false;
        }
        return $this->render(
            'CanalTPScheduleBundle:Form:multimodal.html.twig',
            array(
                'display' => $showMultimodal,
                'navitia_response' => $navitiaResponse,
                'options' => $this->manageOptions($request->query->all(), $options, 'search')
            )
        );
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function multimodalPrecisionAction(Request $request)
    {
        $navitiaAvailabilityService = $this->get('navitia.availability');
        $navitiaResponse = $navitiaAvailabilityService->callApi();
        return $this->render(
            'CanalTPScheduleBundle:Multimodal:precision.html.twig',
            array(
                'navitia_response' => $navitiaResponse
            )
        );
    }

    /**
     * Function to get the multimodal result template
     */
    public function multimodalResultAction(Request $request)
    {
        $this->container->set('request', $request);
        $processor = $this->get('canaltp_journey.multimodal_form.processor');
        $processor->processForm($request);
        $result = $processor->getMainResult();
        $navitiaAvailabilityService = $this->get('navitia.availability');
        $navitiaResponse = $navitiaAvailabilityService->callApi();
        if (!$processor->isMainResultReady() || $result->hasErrors()) {
            $route = ($processor->needPrecision()) ? 'multimodal_form_precision' : 'schedule_home';
            $response = new RedirectResponse(
                $this->get('router')->generate($route, $request->query->all())
            );
        } else {
            $response = $this->render(
                'CanalTPScheduleBundle:Multimodal:result.html.twig',
                array(
                    'navitia_response' => $navitiaResponse
                )
            );
        }
        return $response;
    }

    /**
     * Manage options
     * for the moment manage accordion opening
     * @param Request $request
     * @param array $options
     * @param string $formName
     * @return array
     */
    private function manageOptions($request, $options, $formName)
    {
        if (array_key_exists($formName, $request) === false) {
            $options['display'] = false;
        }
        return $options;
    }

    /**
     * Function to redirect mb
     * @param \CanalTP\Schedule\Controller\request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function redirectMbAction(Request $request)
    {
        if ($request->query->has('method')) {
            switch ($request->query->get('method')) {
                case 'DepartureBoard':
                    $response = $this->getDepartureBoardResponse($request);
                    break;
                case 'Schedule':
                case 'lineComplete':
                    $response = $this->getLineCompleteResponse($request);
                    break;
                default:
                    throw new NotFoundHttpException();
            }
        } else {
            $response = $this->getDepartureBoardResponse($request);
        }
        return $response;
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function getDepartureBoardResponse(Request $request)
    {
        $processor = $this->get('canaltp_schedule.mb.processor');
        $processor->setFormType('schedule');
        $newRequest = $processor->processRequest($request);

        return new RedirectResponse(
            $this->get('router')->generate(
                'schedule_form_result',
                $newRequest->query->all()
            ),
            301
        );
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function getLineCompleteResponse(Request $request)
    {
        $config = $this->container->getParameter('schedule.form');
        $excludedNetworks = $config['route_schedules']['excluded']['network'];
        $processor = $this->get('canaltp_schedule.mb.processor');
        $processor->setFormType('lineSchedule');
        $sens = $request->query->get('sens');
        $newRequest = $processor->processRequest($request);
        $lineScheduleParams = $newRequest->query->get('lineSchedule');
        $routeId = $this->getRouteIdFromSens($lineScheduleParams['line'], $sens);
        $newRequest->query->add(array('lineSchedule[route]' => $routeId));
        $networkId = $this->getNetworkIdFromLine($lineScheduleParams['line']);
        $newRequest->query->add(array('lineSchedule[network]' => $networkId));
        if (in_array($networkId, $excludedNetworks)) {
            throw new NotFoundHttpException('No schedule for network "'.$networkId.'"');
        }
        return new RedirectResponse(
            $this->get('router')->generate(
                'line_schedule_form_result',
                $newRequest->query->all()
            ),
            301
        );
    }

    /**
     * @param $lineId
     * @param $sens
     * @return null
     */
    private function getRouteIdFromSens($lineId, $sens)
    {
        $result = null;
        if ($lineId) {
            $linesApi = $this->get('navitia.lines');
            $linesRequest = $linesApi->generateRequest();
            $linesRequest->addToPathFilter('lines', $lineId);
            $result = $linesApi->callApi($linesRequest)->getResult();
            if (isset($result->lines[0]->routes)) {
                $routes = $result->lines[0]->routes;
                if ($sens == -1 && isset($routes[1])) {
                    $routeId = $routes[1]->id;
                } else {
                    $routeId = $routes[0]->id;
                }
                $result = $routeId;
            }
        }
        if ($result === null) {
            throw new NotFoundHttpException('Route was not found for line "'.$lineId.'"');
        }
        return $result;
    }

    /**
     * @param $lineId
     * @return null
     */
    private function getNetworkIdFromLine($lineId)
    {
        $result = null;
        if ($lineId) {
            $networksApi = $this->get('navitia.networks');
            $networksRequest = $networksApi->generateRequest();
            $networksRequest->addToPathFilter('lines', $lineId);
            $result = $networksApi->callApi($networksRequest)->getResult();
            if (isset($result->networks[0]->id)) {
                $result = $result->networks[0]->id;
            }
        }
        if ($result === null) {
            throw new NotFoundHttpException('Network was not found for line "'.$lineId.'"');
        }
        return $result;
    }

    /**
     * @param $result
     * @return array
     */
    public function translateErrorMessage($result)
    {
        $this->translator = $this->container->get('translator');
        $local_language = $this->container->get('request')->getLocale();
        $this->translator->setLocale($local_language);
        if (isset($result->error)) {
            if (isset($result->error->id)) {
                $translatedMessage = $this->translator->trans($result->error->id, array(), 'navitia');
                $result->error->message = $translatedMessage;
            } elseif (isset($result->error[0])) {
                $translatedMessage = $this->translator->trans(strtolower($result->error[0]), array(), 'navitia');
                $result->error[0] = $translatedMessage;
            }
        } elseif (is_array($result)) {
            if (isset($result['error'])) {
                if ('no result' == $result['error']) {
                    $translatedMessage = $this->translator->trans('no_solution', array(), 'navitia');
                    $result['error'] = $translatedMessage;
                } else {
                    $translatedMessage = $this->translator->trans(strtolower($result['error']), array(), 'navitia');
                    $result['error'] = $translatedMessage;
                }
            }
        }

        return $result;
    }

    /**
     * @param Request $request
     * @param null $network_id
     * @param null $mode_id
     * @return JsonResponse
     */
    public function networksAction(Request $request, $network_id = null, $mode_id = null)
    {
        $accessor = PropertyAccess::createPropertyAccessor();
        $status = 200;
        try {
            $parameters = array();
            $config = $this->container->getParameter('schedule.form');
            $service = $this->container->get('navitia.networks');
            $entity = $service->generateRequest();
            //Manage region
            if ($entity->getRegion() === null) {
                $accessor->setValue(
                    $entity,
                    'region',
                    $this->container->getParameter('navitia.region')
                );
            }
            if ($network_id !== null) {
                //Action for a network
                $entity->addToPathFilter('networks', $network_id);
                $search = 'networks/'.$network_id.'/';
                $pos = strpos($request->getPathInfo(), $search);
                $path = explode('/', substr($request->getPathInfo(), $pos + strlen($search)));
                $nb_path = count($path);
                for ($i = 0; $i < $nb_path; $i = $i + 2) {
                    if ($i + 1 >= $nb_path) {
                        $entity->setAction($path[$i]);
                        if ($path[$i] === 'lines') {
                            //Manage count line
                            if (isset($config['route_schedules']['lines_count'])) {
                                $parameters['count'] = $config['route_schedules']['lines_count'];
                            }
                        }
                        //Manage filter
                        if (isset($config['route_schedules']['excluded']['line'])) {
                            // Récupération de la liste des réseaux exclus
                            $routeSchedulesConfig = $this->config['route_schedules'];
                            $routeSchedulesConfig['excluded'] = $this->config['route_schedules']['excluded']['line'];
                            $routeSchedulesConfig['included'] = $this->config['route_schedules']['included']['line'];
                            $excludedNetworks = $this->container->get('excluded_networks_list_handler')->getList($routeSchedulesConfig);

                            if (count($excludedNetworks) > 0) {
                                $parameters['forbidden_id'] = $excludedNetworks;
                            }
                        }
                        break;
                    }
                    $entity->addToPathFilter($path[$i], $path[$i+1]);
                }
            } else {
                //List of network -> manage filter network
                $excluded_networks = $config['route_schedules']['excluded']['network'];
                if (isset($excluded_networks) && count($excluded_networks) > 0) {
                    $parameters['forbidden_id'] = $excluded_networks;
                }
            }
            //Manage specific filter
            foreach ($request->query->all() as $param => $value) {
                $parameters[$param] = $value;
            }
            if (count($parameters) > 0) {
                $entity->setParameters($parameters);
            }
            $response = $service->callApi($entity);
            $data = $response->getResult();
            $data = $this->translateErrorMessage($data);
        } catch (NoSuchPropertyException $ex) {
            $data = array(
                'error' => sprintf("parameter '%s' not supported", $param)
            );
            $status = 400;
        }
        return new JsonResponse($data, $status);
    }

    /**
     * @param Request $request
     * @param null $mode_id
     * @return JsonResponse
     */
    public function physicalModesAction(Request $request, $mode_id = null)
    {
        $accessor = PropertyAccess::createPropertyAccessor();
        $status = 200;
        try {
            $parameters = array();
            $config = $this->container->getParameter('schedule.form');
            $service = $this->container->get('navitia.physical_modes');
            $entity = $service->generateRequest();
            //Manage region
            if ($entity->getRegion() === null) {
                $accessor->setValue(
                    $entity,
                    'region',
                    $this->container->getParameter('navitia.region')
                );
            }
            if ($mode_id !== null) {
                //Action for a network
                $entity->addToPathFilter('physical_modes', $mode_id);
                $search = 'physical_modes/'.$mode_id.'/';
                $pos = strpos($request->getPathInfo(), $search);
                $path = explode('/', substr($request->getPathInfo(), $pos + strlen($search)));
                $nb_path = count($path);
                for ($i = 0; $i < $nb_path; $i = $i + 2) {
                    if ($i + 1 >= $nb_path) {
                        $entity->setAction($path[$i]);
                        if ($path[$i] === 'lines') {
                            //Manage count line
                            if (isset($config['route_schedules']['lines_count'])) {
                                $parameters['count'] = $config['route_schedules']['lines_count'];
                            }
                        }
                        break;
                    }
                    $entity->addToPathFilter($path[$i], $path[$i+1]);
                }
            }
            //Manage specific filter
            foreach ($request->query->all() as $param => $value) {
                $parameters[$param] = $value;
            }
            if (count($parameters) > 0) {
                $entity->setParameters($parameters);
            }
            $response = $service->callApi($entity);
            $data = $response->getResult();
            $data = $this->translateErrorMessage($data);
        } catch (NoSuchPropertyException $ex) {
            $data = array(
                'error' => sprintf("parameter '%s' not supported", $param)
            );
            $status = 400;
        }
        return new JsonResponse($data, $status);
    }

    /**
     * @return JsonResponse
     */
    public function linesAction()
    {
        $accessor = PropertyAccess::createPropertyAccessor();
        $status = 200;
        $service = $this->container->get('navitia.lines');
        $entity = $service->generateRequest();
        //Manage region
        if ($entity->getRegion() === null) {
            $accessor->setValue(
                $entity,
                'region',
                $this->container->getParameter('navitia.region')
            );
        }
        $response = $service->callApi($entity);
        $data = $response->getResult();
        $data = $this->translateErrorMessage($data);
        return new JsonResponse($data, $status);
    }

    /**
     * @param Request $request
     * @param null $route_id
     * @return JsonResponse
     */
    public function routeSchedulesAction(Request $request, $route_id = null)
    {
        $accessor = PropertyAccess::createPropertyAccessor();
        $service = $this->container->get('navitia.route_schedules');
        $entity = $service->generateRequest();
        if ($route_id !== null) {
            $entity->setPathFilter('routes/'.$route_id);
        }
        $status = 200;
        try {
            $limit_date_times = $this->getParamFromRequest($request, 'limit_date_times', false);
            // default is current time (based on selected time zone)
            $limit_from_datetime = $this->getParamFromRequest($request, 'limit_from_datetime', date('Ymd\THis'));
            foreach ($request->query->all() as $param => $value) {
                $accessor->setValue($entity, $param, $value);
            }
            $response = $service->callApi($entity);
            $data = $this->deleteFormattedCell($response->getResult());
            if ($limit_date_times) {
                $data = $this->filterDateTimes($data, $limit_date_times, $limit_from_datetime);
            }
        } catch (NoSuchPropertyException $ex) {
            $data = array(
                'error' => sprintf("parameter '%s' not supported", $param)
            );
            $status = 400;
        }
        $data = $this->translateErrorMessage($data);
        return new JsonResponse($data, $status);
    }


    /**
     * Function to get a param and remove it from request
     * @param Request $request
     * @param String $paramName
     * @param $default default value if param is not found
     * @return
     */
    private function getParamFromRequest(Request $request, $paramName, $default)
    {
        if ($request->query->has($paramName)) {
            $default = $request->get($paramName);
            $request->query->remove($paramName);
        }
        return $default;
    }

    /**
     * Function to filter datetimes, different from navitia max_date_times since it ignores vehicle journeys
     * @param object $datas
     * @return object
     */
    private function filterDateTimes($response, $limit, $limit_from_datetime)
    {
        if (isset($response->route_schedules) && count($response->route_schedules) > 0) {
            $limit_from_datetime = \DateTime::createFromFormat('Ymd\THis', $limit_from_datetime);
            foreach ($response->route_schedules as $schedule) {
                if (isset($schedule->table->rows) && count($schedule->table->rows) > 0) {
                    foreach ($schedule->table->rows as $row) {
                        $datetimesToKeep = [];
                        foreach ($row->date_times as $dateTimeRow) {
                            $dateTime = \DateTime::createFromFormat('Ymd\THis', $dateTimeRow->date_time);
                            if ($dateTime >= $limit_from_datetime) {
                                array_push($datetimesToKeep, $dateTimeRow);
                            }
                            if (count($datetimesToKeep) >= $limit) {
                                break;
                            }
                        }
                        $row->date_times = $datetimesToKeep;
                    }
                    // keep as many headers as datetimes
                    $schedule->table->headers = array_slice($schedule->table->headers, 0, $limit);
                }
            }
        }
        return $response;
    }

    /**
     * Function to delete formattedCell for ws
     * in order to wait the refacto in routeSchedulesService
     * @param object $datas
     * @return object
     */
    private function deleteFormattedCell($datas)
    {
        if (isset($datas->route_schedules) && count($datas->route_schedules) > 0) {
            foreach ($datas->route_schedules as $data) {
                if (isset($data->formatted_cell)) {
                    unset($data->formatted_cell);
                }
            }
        }
        return $datas;
    }

    /**
     * @param Request $request
     * @param $route_id
     * @param $stop_point_param_name
     * @param $stop_point_id
     * @return JsonResponse
     */
    public function stopSchedulesAction(Request $request, $route_id, $stop_point_param_name, $stop_point_id)
    {
        $accessor = PropertyAccess::createPropertyAccessor();
        $service = $this->container->get('navitia.stop_schedules');
        $entity = $service->generateRequest();
        if ($route_id !== null) {
            $entity->setPathFilter('routes/'.$route_id.'/'.$stop_point_param_name.'/'.$stop_point_id);
        }
        $status = 200;
        try {
            foreach ($request->query->all() as $param => $value) {
                $accessor->setValue($entity, $param, $value);
            }
            $response = $service->callApi($entity);
            $data = $response->getResult();
            if (isset($data->stop_schedules) && count($data->stop_schedules) > 0) {
                $data = $data->stop_schedules[0];
            } elseif (isset($data->error)) {
                $error = 'no result';
                if (is_array($data->error)) {
                    $error = isset($data->error[0]) ? $data->error[0] : 'no result';
                } else {
                    $error = isset($data->error->message) ? $data->error->message : 'no result';
                }
                $data = array(
                    'error' => $error
                );
                $status = 400;
            } else {
                throw new \Exception("no result");
            }
        } catch (NoSuchPropertyException $ex) {
            $data = array(
                'error' => sprintf("parameter '%s' not supported", $param)
            );
            $status = 400;
        } catch (\Exception $e) {
            $data = array(
                'error' => $e->getMessage()
            );
            $status = 400;
        }
        $data = $this->translateErrorMessage($data);
        return new JsonResponse($data, $status);
    }

    /**
     * @param $data
     * @param $limit
     * @return mixed
     */
    private function filterDepartures($data, $limit)
    {
        if (isset($data->departures) && count($data->departures) > $limit) {
            $data->departures = array_slice($data->departures, 0, $limit);
        }
        return $data;
    }

    /**
     * @param Request $request
     * @param $route_param_name
     * @param $route_id
     * @param $stop_point_param_name
     * @param $stop_point_id
     * @return JsonResponse
     */
    public function departuresAction(Request $request, $route_param_name, $route_id, $stop_point_param_name, $stop_point_id)
    {
        $accessor = PropertyAccess::createPropertyAccessor();
        $service = $this->container->get('navitia.departures');
        $entity = $service->generateRequest();
        if ($route_id !== '') {
            $entity->setPathFilter('routes/'.$route_id.$stop_point_param_name.$stop_point_id);
        } else {
            $entity->setPathFilter($stop_point_param_name.$stop_point_id);
        }
        $status = 200;
        try {
            $limitDepartures = $this->getParamFromRequest($request, 'limit_departures', 2);
            foreach ($request->query->all() as $param => $value) {
                $accessor->setValue($entity, $param, $value);
            }
            $response = $service->callApi($entity);
            $data = $response->getResult();
            $data = $this->filterDepartures($data, $limitDepartures);
        } catch (NoSuchPropertyException $ex) {
            $data = array(
                'error' => sprintf("parameter '%s' not supported", $param)
            );
            $status = 400;
        }
        $data = $this->translateErrorMessage($data);
        return new JsonResponse($data, $status);
    }
}
