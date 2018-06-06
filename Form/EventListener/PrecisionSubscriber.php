<?php

namespace CanalTP\ScheduleBundle\Form\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Description of PrecisionSubscriber
 *
 * @author JRE <johan.rouve@canaltp.fr>
 * @copyright Canal TP (c) 2013
 **/
class PrecisionSubscriber implements EventSubscriberInterface
{
    private $placesApi;
    private $request;
    private $processor;
    private $event;

    /**
     * __construct
     *
     * Injection de l'api Places
     *
     * @author JRE <johan.rouve@canaltp.fr>
     * @copyright Canal TP (c) 2013
     *
     * @param ContainerInterface $container
     **/
    public function __construct(ContainerInterface $container)
    {
        $this->placesApi = $container->get('navitia.places');
        $this->request = $container->get('request');
        $this->processor = $container->get('canaltp_schedule.form.processor');
    }

    /**
     * {@inheritDoc}
     **/
    public static function getSubscribedEvents()
    {
        return array(
            FormEvents::PRE_SET_DATA => 'preSetData'
        );
    }

    /**
     * preSetData
     *
     * Ajoute les champs de pouvant etre ambigues
     *
     * @author JRE <johan.rouve@canaltp.fr>
     * @copyright Canal TP (c) 2013
     *
     * @param FormEvent $event
     **/
    public function preSetData(FormEvent $event)
    {
        $this->event = $event;
        $query = $this->request->query->all();
        $autocomplete = $this->processor->prepareAutocompleteDatas();
        foreach ($autocomplete as $fieldConfig) {
            $fieldName = $fieldConfig->getFieldName();
            if (isset($query[key($query)][$fieldName])) {
                $this->addField($fieldConfig->getFieldName());
            }
        }
    }

    /**
     * addField
     *
     * Ajoute le type de champs adapté,
     * type choice si le champs est ambigue
     * sinon type autocomplete
     *
     * @author JRE <johan.rouve@canaltp.fr>
     * @copyright Canal TP (c) 2013
     *
     * @param String $fieldName
     **/
    public function addField($fieldName)
    {
        if ($this->processor->needPrecision($fieldName)) {
            $this->addChoiceField($fieldName);
        } else {
            $this->addHiddenAutocompleteField($fieldName);
        }
    }

    /**
     * addChoiceField
     *
     * Ajoute un champs choice
     *
     * @author JRE <johan.rouve@canaltp.fr>
     * @copyright Canal TP (c) 2013
     *
     * @param String $fieldName
     **/
    public function addChoiceField($fieldName)
    {
        $form = $this->event->getForm();
        $data = $this->processor->getAutocompleteData($fieldName);
        $choices = $this->getChoices($data['autocomplete']);
        $form->add(
            $fieldName,
            'choice',
            array(
                'label'  => 'schedule.form.'.$fieldName.'.label',
                'choices'  => $choices,
                'expanded' => true,
                'data' => key($choices),
                'property_path' => 'path_filter',
                'auto_initialize' => false
            )
        );
    }

    /**
     * addHiddenAutocompleteField
     *
     * Ajoute un champs autocomplete_hidden
     *
     * @author JRE <johan.rouve@canaltp.fr>
     * @copyright Canal TP (c) 2013
     *
     * @param String $fieldName
     **/
    public function addHiddenAutocompleteField($fieldName)
    {
        $form = $this->event->getForm();
        $data = $this->processor->getAutocompleteData($fieldName);
        $form->add($fieldName, 'hidden_autocomplete', array('data' => $data));
    }

    /**
     * getChoices
     *
     * Appel à l'API Places et parse le resultat
     *
     * @author JRE <johan.rouve@canaltp.fr>
     * @copyright Canal TP (c) 2013
     *
     * @param String $value
     **/
    public function getChoices($value)
    {
        $choices = array();
        $request = array(
            'q' => $value,
            'type' => array('stop_area')
        );
        $this->placesApi->suggest($request);
        $response = $this->placesApi->getResponse();
        $result = json_decode($response);
        foreach ($result->places as $place) {
            $value = $place->id.'|'.$place->name;
            $label = $place->name;
            $choices[$value] = $label;
        }
        return $choices;
    }
}
