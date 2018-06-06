<?php

namespace CanalTP\ScheduleBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\Definition\Processor;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class CanalTPScheduleExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $processor = new Processor();
        $configuration = new Configuration();
        $config = $processor->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        $form = (isset($config['form']))? $config['form'] : array();
        $result = (isset($config['result']))? $config['result'] : array();
        $container->setParameter('schedule.form', $form);
        $container->setParameter('schedule.result', $result);
        $container->setParameter('javascript.schedule', $config['javascript']);
        $container->setParameter('journey.content_identifier', $config['content_identifier']);

        if (isset($config['emails'])) {
            $container->setParameter('schedule.emails', $config['emails']);
            $container->setParameter('line_schedule.emails', $config['emails']);
        }
        if (isset($config['enabled_catch_message'])) {
            $container->setParameter('schedule.enabled_catch_message', $config['enabled_catch_message']);
        }
        $container->setParameter('schedule.options', $config['options']);
    }
}
