<?php

namespace CanalTP\ScheduleBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use CanalTP\FrontCoreBundle\Form\DataTransformer\DatetimeToIsoTransformer;
use CanalTP\ScheduleBundle\Form\DataTransformer\UriToFilterTransformer;
use CanalTP\FrontCoreBundle\I18n\IntlDateFormat;

class ScheduleType extends AbstractType
{
    private $container;

    /**
     * ScheduleType constructor.
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
        $params = $this->container->getParameter('schedule.form');
        $timezone = $this->container->getParameter('timezone');
        $config = $params['autocomplete'];
        $builder
            ->add(
                $builder->create(
                    'stop_area',
                    'autocomplete',
                    array(
                        'label'  => 'schedule.form.stop_area.label',
                        'attr' => array(
                            'title' => 'schedule.form.stop_area.title',
                            'placeholder' => 'schedule.form.stop_area.placeholder',
                            'data-group' => $config['stop_area'],
                        ),
                        'property_path' => 'path_filter'
                    )
                )
                ->addModelTransformer(new UriToFilterTransformer())
            )
            ->add(
                $builder->create(
                    'from_datetime',
                    'future_datetime',
                    array(
                        'date_widget' => 'single_text',
                        'date_format' => $this->container->get('canaltp.i18n.date_formats')->getShortIcuFormat(),
                        'minutes' => range(0, 59),
                        'label'  => 'schedule.form.datetime'
                    )
                )
                ->addModelTransformer(new DatetimeToIsoTransformer($timezone))
            );
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'csrf_protection' => false,
        ));
    }

    public function getName()
    {
        return 'schedule';
    }
}
