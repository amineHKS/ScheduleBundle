<?php

namespace CanalTP\ScheduleBundle\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DateHiddenTimeType extends DateTimeType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $tempBuilder = clone $builder;
        parent::buildForm($tempBuilder, $options);

        $viewTrans = $tempBuilder->getViewTransformers();
        foreach ($viewTrans as $transformer) {
            $builder->addViewTransformer($transformer);
        }

        $fields = array('date' => false, 'time' => true);
        foreach ($fields as $field => $hidden) {
            if ($tempBuilder->has($field)) {
                $options = $tempBuilder->get($field)->getOptions();
                $options['label'] = false;
                if ($hidden) {
                    $type = 'hidden_'.$field;
                } else {
                    $type = $field;
                }
                $builder->add($field, $type, $options);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults(
            array(
                'label' => false
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return 'form';
    }

    public function getName()
    {
        return 'date_hidden_time';
    }
}
