<?php

namespace CanalTP\ScheduleBundle\Service\Files;

/**
 * File service interface
 *
 * @author Vincent Catillon <vincent.catillon@canaltp.fr>
 */
interface FileServiceInterface
{
    /**
     * File link getter
     * @param array $params
     */
    public function getLink(array $params);
}
