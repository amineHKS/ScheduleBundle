<?php
namespace CanalTP\ScheduleBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class DateRange extends Constraint
{
    public $maxMessage = 'schedule.datetime.errors.max';
    public $invalidMessage = 'schedule.datetime.errors.invalid';

    public function validatedBy()
    {
        return 'date_range_schedule';
    }
}
