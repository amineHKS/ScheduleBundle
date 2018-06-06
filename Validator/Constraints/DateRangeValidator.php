<?php
namespace CanalTP\ScheduleBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\DependencyInjection\Container;

class DateRangeValidator extends ConstraintValidator
{

    private $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * {@inheritDoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (null === $value) {
            return;
        }
        $datetimeValue = new \DateTime($value);
        //$metadatas = $this->container->getParameter('navitia.metadatas');
        $metadatas = $this->container->get('navitia.metadatas')->getMetadatas();
        if (isset($metadatas->end_production_date)) {
            $max = \DateTime::createFromFormat('Ymd', $metadatas->end_production_date);
            if ($datetimeValue > $max) {
                $this->context->buildViolation($constraint->maxMessage)
                ->addViolation();
            }
        }
    }
}
