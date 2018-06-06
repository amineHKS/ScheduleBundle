<?php

namespace CanalTP\ScheduleBundle\Tests\Functional\Service;

use Symfony\Component\PropertyAccess\PropertyAccess;
use CanalTP\PlacesBundle\Entity\NavitiaResponse;
use CanalTP\ScheduleBundle\Service\StopSchedulesService;

/**
 * Class for processing Navitia raw response
 * @author Vincent Catillon <vincent.catillon@canaltp.fr>
 */
class StopSchedulesServiceTest extends \PHPUnit_Framework_TestCase
{
    protected $result;
    protected $accessor;

    protected function setUp()
    {
        $this->accessor = PropertyAccess::getPropertyAccessor();
        $json = file_get_contents(__DIR__.'/Fixtures/stopSchedules.json');
        if ($json !== false) {
            $this->result = json_decode($json);
        }
    }

    public function testHandleStopSchedules()
    {
        $stopSchedulesService = new StopSchedulesService();
        $stopSchedulesReflection = new \ReflectionObject($stopSchedulesService);
        $setTimetableMethod = $stopSchedulesReflection->getMethod('setTimetable');
        $processFilesMethod = $stopSchedulesReflection->getMethod('processFiles');
        $processFilesMethod->setAccessible(true);

        $cases = array(
            null,
            'http://domain.tld/path/to/file.pdf'
        );
        foreach ($cases as $link) {
            $timetableService = $this->getMockBuilder('CanalTP\\ScheduleBundle\\Service\\Files\\TimetableService')
                ->getMock();
            $timetableService->expects($this->any())
                ->method('getLink')
                ->will($this->returnValue($link));

            $setTimetableMethod->invoke($stopSchedulesService, $timetableService);
            $navitiaResponse = new NavitiaResponse();
            $navitiaResponse->setRaw($this->result);
            $processFilesMethod->invoke($stopSchedulesService, $navitiaResponse);

            $file = $this->accessor->getValue($navitiaResponse->getResult(), 'stop_schedules[0].files.timetable');
            $this->assertEquals($file, $link);
        }
    }
}
