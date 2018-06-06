<?php

namespace CanalTP\ScheduleBundle\Form\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\FormBuilderInterface;
use CanalTP\ScheduleBundle\Form\DataTransformer\UriToFilterTransformer;

/**
 * Description of LineScheduleSubscriber
 *
 * @author RNE <ramatoulaye.ndiaye@canaltp.fr>
 * @copyright Canal TP (c) 2013
 **/
class LineScheduleSubscriber implements EventSubscriberInterface
{
    private $networksApi;
    private $linesApi;
    private $routesApi;
    private $request;
    private $event;
    private $builder;
    private $config;
    private $container;

    /**
     * __construct
     *
     * Injection des API networks, lines
     *
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     */
    public function __construct(ContainerInterface $container, FormBuilderInterface $builder)
    {
        $this->networksApi = $container->get('navitia.networks');
        $this->linesApi = $container->get('navitia.lines');
        $this->routesApi = $container->get('navitia.routes');
        $this->request = $container->get('request');
        $this->builder = $builder;
        $this->config = $container->getParameter('schedule.form');
        $this->container = $container;
    }

    /**
     * {@inheritDoc}
     **/
    public static function getSubscribedEvents()
    {
        return array(
            FormEvents::PRE_SET_DATA => 'preSetData',
            FormEvents::PRE_SUBMIT => 'preSubmit'
        );
    }

    /**
     * preSetData
     *
     * Ajout du champ Networks avec la liste des réseaux
     *
     * @param \Symfony\Component\Form\FormEvent $event
     */
    public function preSetData(FormEvent $event)
    {
        $parameters = array();
        $this->event = $event;

        // Récupération de la liste des réseaux exclus
        $routeSchedulesConfig = $this->config['route_schedules'];
        $routeSchedulesConfig['excluded'] = $this->config['route_schedules']['excluded']['network'];
        $routeSchedulesConfig['included'] = $this->config['route_schedules']['included']['network'];
        $excludedNetworks = $this->container->get('excluded_networks_list_handler')->getList($routeSchedulesConfig);

        if (isset($this->config['route_schedules']['networks_count'])) {
            $parameters['count'] = $this->config['route_schedules']['networks_count'];
        }
        if (isset($excludedNetworks) && count($excludedNetworks) > 0) {
            $parameters['forbidden_id'] = $excludedNetworks;
        }
        $data = $this->getApiData($this->networksApi, null, $parameters);
        $choices = $this->getChoices($data, 'networks');
        if (count($choices) == 1) {
            $this->addTextField($choices, 'network', false);
            $this->addLineField($data->networks[0]->id);
        } else {
            $this->addChoiceField($choices, 'network', false);
        }
    }

    /**
     * preSubmit
     *
     * Fonction permettant de rajouter un champ en fonction d'un événement
     *
     * @param \Symfony\Component\Form\FormEvent $event
     */
    public function preSubmit(FormEvent $event)
    {
        $this->event = $event;
        $data = $this->event->getData();
        if (isset($data['network'])) {
            $this->addLineField($data['network']);
        }
        if (isset($data['line'])) {
            $routeFilter = array(
                'lines' => $data['line'],
                'networks' => $data['network']
            );
            $routeData = $this->getApiData($this->routesApi, $routeFilter, array('depth' => 0));
            if (isset($routeData->routes)) {
                $routesChoice = $this->getChoices($routeData, 'routes');
                $this->addChoiceField($routesChoice, 'route', true, 'path_filter');
            }
        }
    }

    /**
     * Function to get the coverage parameters
     * @param string $network
     * @return array
     */
    public function getParameters($network)
    {
        $parameters = array();
        if (isset($this->config['route_schedules']['lines_count'])) {
            $parameters['count'] = $this->config['route_schedules']['lines_count'];
        }
        if (isset($this->config['route_schedules']['excluded']['line'])) {
            $forbidden_id = !empty($this->config['route_schedules']['excluded']['line']) ? $this->config['route_schedules']['excluded']['line'] : array();
            if (count($forbidden_id) > 0) {
                $parameters['forbidden_id'] = $forbidden_id;
            }
        }
        return $parameters;
    }

    /**
     * getChoices
     *
     * Fonction permettant de récupérer la liste des choix (object: networks, lines)
     *
     * @param array $data
     * @param String $type
     *
     * @return array
     */
    public function getChoices($data, $type)
    {
        $choices = array();
        if (isset($data->$type)) {
            foreach ($data->$type as $object) {
                $value = $object->id;
                $label = '';
                if (isset($object->commercial_mode)) {
                    $label .= $object->commercial_mode->name.' ';
                }
                if (isset($object->code)) {
                    $label .= $object->code.' ';
                }
                $label .= $object->name;

                $choices[$label] = $value;
            }
        }
        return $choices;
    }

    /**
     * addChoiceField
     *
     * Fonction permettant d'avoir le champ choice (select) du formulaire
     *
     * @param array $choices
     * @param String $fieldName
     * @param bool $mapped
     * @param String $propertyPath
     */
    public function addChoiceField($choices, $fieldName, $mapped = true, $propertyPath = '')
    {
        $form = $this->event->getForm();
        $propertyPath = ($propertyPath == '') ? $fieldName : $propertyPath;
        $data = \key($choices);
        $form->add(
            $this->builder->create(
                $fieldName,
                'choice',
                array(
                    'label'  => 'line_schedule.form.'.$fieldName.'.label',
                    'choices' => $choices,
                    'choices_as_values' => true,
                    'mapped' => $mapped,
                    'data' => \in_array($data, $choices) ? $choices[$data] : null,
                    'property_path' => $propertyPath,
                    'empty_value' => 'line_schedule.form.'.$fieldName.'.empty_value',
                    'auto_initialize' => false
                )
            )
            ->addModelTransformer(new UriToFilterTransformer())
            ->getForm()
        );
    }

    /**
     * addTextField
     *
     * Fonction permettant d'avoir le champ text (input) du formulaire
     *
     * @param array $choices
     * @param String $fieldName
     * @param bool $mapped
     * @param String $propertyPath
     */
    public function addTextField($choices, $fieldName, $mapped = true, $propertyPath = '')
    {
        $form = $this->event->getForm();
        $propertyPath = ($propertyPath == '') ? $fieldName : $propertyPath;
        $data = \key($choices);
        $form->add(
            $this->builder->create(
                $fieldName,
                'hidden',
                array(
                    'label'  =>  $data,
                    'label_attr' => array('class' => 'label-mono-network'),
                    'data' => $data && isset($choices[$data]) ? $choices[$data] : null,
                    'mapped' => $mapped,
                    'property_path' => $propertyPath,
                    'auto_initialize' => false
                )
            )
            ->getForm()
        );
    }

    /**
     * addLineField
     *
     * Fonction permettant d'avoir le champ (select) de ligne du formulaire
     *
     *@param array $network
     *
     *
     */
    public function addLineField($network)
    {
        $networkFilter = array(
            'networks' => $network
        );
        $parameters = $this->getParameters($network);
        $parameters['depth'] = 0;
        $linesData = $this->getApiData($this->linesApi, $networkFilter, $parameters);
        $linesChoice = $this->getChoices($linesData, 'lines');
        $this->addChoiceField($linesChoice, 'line', false);
    }

    /**
     * Fonction permettant de récupérer les données en fonction de l'api
     *
     * @param String $api
     * @param array $filters
     * @param array $parameters
     *
     * @return array
     */
    public function getApiData($api, array $filters = null, array $parameters = null)
    {
        $entity = $api->generateRequest();
        if ($filters != null) {
            foreach ($filters as $type => $value) {
                $entity->addToPathFilter($type, $value);
            }
        }
        if ($parameters != null && count($parameters) > 0) {
            $entity->setParameters($parameters);
        }
        $response = $api->callApi($entity);
        $result = $response->getResult();
        return $result;
    }
}
