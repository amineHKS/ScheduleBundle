<?php

namespace CanalTP\ScheduleBundle\Processor;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class MBProcessor implements ContainerAwareInterface
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

    protected $formType;
    protected $collection = array();
    protected $dateDay;
    protected $dateYearMonth;

    const TC_OBJECT_STOP_POINT = 'stop_points';
    const TC_OBJECT_STOP_AREA = 'stop_areas';
    const TC_OBJECT_ROUTE = 'routes';
    const TC_OBJECT_LINE = 'lines';
    const TC_OBJECT_NETWORK = 'networks';

    public function __construct()
    {
        $this->formType = 'schedule';
        $this->collection['stop_area']['autocomplete'] = '';
        $now = new \DateTime();
        $this->setDateDay($now->format('d'));
        $this->setDateYearMonth($now->format('m|Y'));
    }

    public function setFormType($formType)
    {
        $this->formType = $formType;
    }

    /**
     * Function to set stop_area autocomplete item
     * @param string $cityLabel
     */
    public function setCityLibelle($cityLabel)
    {
        $val = $this->collection['stop_area']['autocomplete'] . " " . $cityLabel;
        $this->collection['stop_area']['autocomplete'] = trim($val);
    }

    /**
     * Function to set stop_area autocomplete item
     * @param string $stopAreaLabel
     */
    public function setStopAreaLibelle($stopAreaLabel)
    {
        $val = $this->collection['stop_area']['autocomplete'] . " " . $stopAreaLabel;
        $this->collection['stop_area']['autocomplete'] = trim($val);
    }

    /**
     * Function to set stop_area autocomplete hidden
     * @param string $stopAreaExternalCodes
     */
    public function setStopAreaExternalCodes($stopAreaExternalCodes)
    {
        $uriConverterService = $this->container->get('sqwal.uri.converter');
        $id = $uriConverterService->getIdFromExternalCode(self::TC_OBJECT_STOP_AREA, $stopAreaExternalCodes);
        $this->collection['stop_area']['autocomplete-hidden'] = $id;
    }

    /**
     * Process special edt data from URI
     * @param string $edtStopArea
     */
    public function setEdtStopArea($edtStopArea)
    {
        $edt = explode('=>', urldecode($edtStopArea));
        $this->setStopAreaExternalCodes($edt[1]);
        $this->setStopAreaLibelle($edt[2]);
    }

    /**
     * Function to set line item
     * @param string $lineExternalCode
     */
    public function setLineExternalCode($lineExternalCode)
    {
        $uriConverterService = $this->container->get('sqwal.uri.converter');
        $id = $uriConverterService->getIdFromExternalCode(self::TC_OBJECT_LINE, $lineExternalCode);
        $this->collection['line'] = $id;
    }

    /**
     * Function to set dateDay
     * @param string $dateDay
     */
    public function setDateDay($dateDay)
    {
        $this->dateDay = $dateDay;
    }

    /**
     * Function to set dateYearMonth
     * @param string $dateYearMonth
     */
    public function setDateYearMonth($dateYearMonth)
    {
        $this->dateYearMonth = $dateYearMonth;
    }

    /**
     * Function to process the request
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \Symfony\Component\HttpFoundation\Request
     */
    public function processRequest(Request $request)
    {
        $requestArray = $request->query->all();
        foreach ($requestArray as $key => $parameter) {
            $method = 'set'.ucfirst($key);
            if (method_exists(new MBProcessor, $method)) {
                $this->$method($parameter);
            }
        }
        $this->processDate();
        $keys = $request->query->keys();
        foreach ($keys as $key) {
            $request->query->remove($key);
        }
        $request->query->add(array($this->formType => $this->collection));
        return $request;
    }

    public function processDate()
    {
        if (isset($this->dateDay) && isset($this->dateYearMonth)) {
            if ($this->dateDay) {
                $date = sprintf("%02s", $this->dateDay);
                $yearMonth = explode("|", $this->dateYearMonth);
                $month = sprintf("%02s", $yearMonth[0]);
                if ($this->formType == 'schedule') {
                    $this->collection['from_datetime']['date'] = $date.'/'.$month.'/'.$yearMonth[1];
                } else {
                    $this->collection['from_datetime'] = $date.'/'.$month.'/'.$yearMonth[1];
                }
            } else {
                $now = new \DateTime();
                if ($this->formType == 'schedule') {
                    $this->collection['from_datetime']['date'] = $now->format('d/m/Y');
                } else {
                    $this->collection['from_datetime'] = $now->format('d/m/Y');
                }
            }
        }
    }
}
