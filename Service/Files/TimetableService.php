<?php

namespace CanalTP\ScheduleBundle\Service\Files;

use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;

/**
 * Timetable service
 * @author Vincent Catillon <vincent.catillon@canaltp.fr>
 */
class TimetableService extends AbstractFileService
{
    /**
     * Api URI
     * @var string $uri
     */
    private $uri;

    /**
     * Networks
     * @var array $networks
     */
    private $networks;

    /**
     * URI setter
     * @param string $uri
     */
    public function setUri($uri)
    {
        $this->uri = $uri;
    }

    /**
     * Networks setter
     * @param array $networks
     */
    public function setNetworks($networks)
    {
        $this->networks = $networks;
    }

    /**
     * File link getter
     * @param array $params
     * @return mixed
     * @throws ParameterNotFoundException
     */
    public function getLink(array $params)
    {
        if (empty($params['network']) || empty($params['route']) || empty($params['stopPoint'])) {
            throw new ParameterNotFoundException(sprintf('You should define network, route and stop point to use %s.', get_class($this)));
        }
        $curlInfo = $this->call($params);

        return !empty($curlInfo['redirect_url']) ? $curlInfo['redirect_url'] : null;
    }

    /**
     * API call function
     * @param String $client
     * @param array $params
     * @return array
     */
    private function call(array $params)
    {
        if (empty($this->uri) || empty($this->networks[$params['network']]['timetable'])) {
            return;
        }

        $curlHandle = curl_init();
        $uri = $this->getApiUri($params);

        curl_setopt($curlHandle, CURLOPT_URL, $uri);
        curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 6);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_FOLLOWLOCATION, false);

        $curlExec = curl_exec($curlHandle);
        $curlInfo = $curlExec && !curl_errno($curlHandle) ? curl_getinfo($curlHandle) : null;
        curl_close($curlHandle);

        return $curlInfo;
    }

    /**
     * Api URI getter
     * @param array $params
     * @return string
     */
    private function getApiUri(array $params)
    {
        $client = $this->networks[$params['network']]['timetable'];
        $uri = $this->uri.'/mtt/customers/'.$client.'/networks/'.$params['network'].'/routes/'.$params['route'].'/stop_points/'.$params['stopPoint'].'/seasons';
        unset($params['network'], $params['route'], $params['stopPoint']);
        if (!empty($params)) {
            $uri .= '?'.http_build_query($params);
        }

        return $uri;
    }
}
