<?php

namespace CanalTP\ScheduleBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use CanalTP\FrontCoreBundle\Form\DataTransformer\DatetimeToIsoTransformer;
use CanalTP\ScheduleBundle\Form\EventListener\PrecisionSubscriber;
use CanalTP\FrontCoreBundle\I18n\IntlDateFormat;

class PrecisionType extends AbstractType
{
    private $container;

    /**
     * PrecisionType constructor.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $timezone = $this->container->getParameter('timezone');
        $builder
            ->add(
                $builder->create(
                    'from_datetime',
                    'hidden_datetime',
                    array(
                        'date_widget' => 'single_text',
                        'date_format' => $this->container->get('canaltp.i18n.date_formats')->getShortIcuFormat(),
                    )
                )
                ->addModelTransformer(new DatetimeToIsoTransformer($timezone))
            )
            ->addEventSubscriber(new PrecisionSubscriber($this->container));
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'csrf_protection' => false,
        ));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'precision';
    }
}
