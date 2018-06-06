<?php

namespace CanalTP\ScheduleBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use CanalTP\ScheduleBundle\Form\EventListener\LineScheduleSubscriber;
use CanalTP\FrontCoreBundle\Form\DataTransformer\DatetimeToIsoTransformer;
use CanalTP\FrontCoreBundle\I18n\IntlDateFormat;
use CanalTP\FrontCoreBundle\I18n\DayPartFormat;

class LineScheduleType extends AbstractType
{
    private $container;

    /**
     * LineScheduleType constructor.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Fonction permettant de construire le formulaire
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $timezone = $this->container->getParameter('timezone');
        $subscriber = new LineScheduleSubscriber($this->container, $builder);
        $scheduleFormConfig = $this->container->getParameter('schedule.form');
        $line_dayparts = array();

        foreach ($scheduleFormConfig['line_daypart'] as $line_daypart) {
            $line_dayparts[$line_daypart] = $line_daypart;
        }

        $builder
            ->add(
                $builder->create(
                    'from_datetime',
                    'future_date',
                    array(
                        'widget' => 'single_text',
                        'format' => $this->container->get('canaltp.i18n.date_formats')->getShortIcuFormat(),
                        'label' => 'line_schedule.form.datetime'
                    )
                )
                    ->addModelTransformer(new DatetimeToIsoTransformer($timezone))
            )
            ->add(
                'line_daypart',
                'choice',
                array(
                    'choices' => DayPartFormat::format($line_dayparts),
                    'label' => 'line_schedule.form.line_daypart',
                    'mapped' => false
                )
            );
        $builder->addEventSubscriber($subscriber);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            array(
                'csrf_protection' => false,
            )
        );
    }

    public function getName()
    {
        return 'lineSchedule';
    }
}
