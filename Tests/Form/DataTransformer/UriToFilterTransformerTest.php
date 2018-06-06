<?php

namespace CanalTP\ScheduleBundle\Tests\Form\DataTransformer;

use CanalTP\ScheduleBundle\Form\DataTransformer\UriToFilterTransformer;

class UriToFilterTransformerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var UriToFilterTransformer
     */
    private $dataTransformer;

    public function setUp()
    {
        $this->dataTransformer = new UriToFilterTransformer();
    }

    /**
     * @return array
     */
    public function provideFilterAndId()
    {
        return [
            ['stop_areas/stop_area:FOO:BAR', 'stop_area:FOO:BAR'],
            ['lines/line:FOO:BAR', 'line:FOO:BAR'],
            ['stop_points/stop_point:FOO:BAR', 'stop_point:FOO:BAR'],
            ['routes/route:BAR', 'route:BAR'],
        ];
    }

    /**
     * @dataProvider provideFilterAndId
     */
    public function testTransformAndReverseTransform($filter, $id)
    {
        $this->assertEquals($id, $this->dataTransformer->transform($filter));
        $this->assertEquals($filter, $this->dataTransformer->reverseTransform($id));
    }

    public function provideBadFilters()
    {
        return [['stop_point'], ['stop_point/'], ['']];
    }

    /**
     * @dataProvider provideBadFilters
     * @expectedException \RuntimeException
     */
    public function testThrowsExceptionOnInvalidFilter($filter)
    {
        $this->dataTransformer->transform($filter);
    }

    public function provideBadIds()
    {
        return [['stop_point'], ['stop_point:'], ['']];
    }

    /**
     * @dataProvider provideBadIds
     * @expectedException \RuntimeException
     */
    public function testThrowsExceptionOnInvalidId($id)
    {
        $this->dataTransformer->reverseTransform($id);
    }
}
