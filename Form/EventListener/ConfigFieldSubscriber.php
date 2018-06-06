<?php

namespace CanalTP\ScheduleBundle\Form\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Description of ConfigFieldSubscriber
 *
 * @author JRE <johan.rouve@canaltp.fr>
 * @copyright Canal TP (c) 2013
 **/
class ConfigFieldSubscriber implements EventSubscriberInterface
{
    private $builder;
    private $container;

    /**
     * __construct
     *
     * Ajoute les dependances
     *
     * @author JRE <johan.rouve@canaltp.fr>
     * @copyright Canal TP (c) 2013
     *
     * @param ContainerInterface $container
     * @param FormBuilderInterface $builder
     **/
    public function __construct(ContainerInterface $container, FormBuilderInterface $builder)
    {
        $this->container = $container;
        $this->builder = $builder;
    }

    /**
     * {@inheritDoc}
     **/
    public static function getSubscribedEvents()
    {
        return array(FormEvents::PRE_SET_DATA => 'preSetData');
    }
}
