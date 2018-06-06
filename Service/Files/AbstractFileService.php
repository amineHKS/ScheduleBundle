<?php

namespace CanalTP\ScheduleBundle\Service\Files;

use Symfony\Component\Intl\Exception\MethodNotImplementedException;

/**
 * Abstract file service
 * @author Vincent Catillon <vincent.catillon@canaltp.fr>
 */
class AbstractFileService implements FileServiceInterface
{
    /**
     * File link getter
     * @param array $params
     * @throws MethodNotImplementedException
     */
    public function getLink(array $params)
    {
        throw new MethodNotImplementedException('Your file service has to override the '.__FUNCTION__.' function.');
    }
}
