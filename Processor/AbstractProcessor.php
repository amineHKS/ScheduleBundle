<?php

namespace CanalTP\ScheduleBundle\Processor;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AbstractProcessor implements ContainerAwareInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * {@inheritDoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @var \CanalTP\PlacesBundle\Entity\NavitiaResponse
     */
    protected $result;
    protected $entity;
    protected $api;
    /**
     * @var \CanalTP\FrontCoreBundle\Service\ResponseService
     */
    protected $responseManager;

    public function getResponseManager()
    {
        return $this->responseManager;
    }

    public function setResponseManager($responseManager)
    {
        $this->responseManager = $responseManager;
        return $this;
    }

    /**
     * Defini la classe d'api a utiliser
     *
     * @param $api
     */
    public function setApi($api)
    {
        $this->api = $api;
        return $this->api;
    }

    public function setEntity($entity)
    {
        $this->entity = $entity;
        return $this->entity;
    }

    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * Appel navitia pour recuper le resultat depuis la requete
     */
    public function call()
    {
        $this->result = $this->api->callApi($this->entity);
        return $this->result;
    }

    /**
     * Renvoie le resultat global des requetes
     *
     * @return \CanalTP\PlacesBundle\Entity\NavitiaResponse
     */
    public function getMainResult()
    {
        return $this->getResponseManager()->getResult();
    }

    /**
     * Permet de savoir si le resultat est exploitable
     *
     * @return boolean
     */
    public function isMainResultReady()
    {
        return $this->getResponseManager()->isReady();
    }
}
