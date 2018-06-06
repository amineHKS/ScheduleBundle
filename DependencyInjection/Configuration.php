<?php

namespace CanalTP\ScheduleBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

/**
 * This is the class that validates and merges configuration from your app/config files
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('canal_tp_schedule');

        $this->addFormConfig($rootNode);
        $this->addJavacriptConfig($rootNode);
        $this->addCmsConfig($rootNode);
        $this->addCatchMessageConfig($rootNode);
        $this->addOptionsConfig($rootNode);
        $this->addDisruptionKey($rootNode);

        return $treeBuilder;
    }

    private function addJavacriptConfig(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->variableNode('javascript')
                ->end()
            ->end();
    }
    private function addCmsConfig(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->variableNode('content_identifier')
                    ->info('configuration des identifiants de contenu au niveau du cms')
                    ->defaultValue('')
                ->end()
            ->end()
        ->end();
    }
    private function addFormConfig(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()

                ->arrayNode('form')
                    ->info('configuration du formulaire')
                    ->children()
                        ->variableNode('autocomplete')
                            ->info('groupes de configuration pour les champs autocomplete')
                        ->end()
                        ->variableNode('next_departures')
                            ->info('configuration pour next_departures (activation, ...)')
                        ->end()
                        ->variableNode('route_schedules')
                            ->info('configuration pour route_schedules (pagination, ...)')
                        ->end()
                        ->variableNode('multimodal')
                            ->info('configuration pour la partie multimodal (entre deux arrêts)')
                        ->end()
                        ->variableNode('other_lines')
                            ->info('configuration pour coverage lines (groupBy, ...)')
                        ->end()
                        ->variableNode('line_daypart')
                            ->info('daypart for line schedule search')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('result')
                    ->info('Liste des lignes')
                    ->children()
                        ->variableNode('modes')
                            ->info('configuration des modes')
                            ->cannotBeEmpty()
                        ->end()
                        ->variableNode('time_slots')
                            ->info('configuration des plages horaires')
                        ->end()
                        ->variableNode('nbNextTime')
                            ->info('configuration du nombre d\'horaire suivant à afficher')
                        ->end()
                        ->variableNode('print_nb_row')
                            ->info('configuration du nombre de colonne à impression par page ')
                        ->end()
                        ->booleanNode('enabled_catch_message')
                            ->info(" activate or not the catch message ")
                            ->defaultValue(false)
                        ->end()
                    ->end()
                ->end()
                ->variableNode('emails')
                ->end()
            ->end();
    }

    private function addCatchMessageConfig(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->booleanNode('enabled_catch_message')
                    ->info(" activate or not the catch message ")
                    ->defaultValue(false)
                ->end()
            ->end()
        ->end();
    }

    private function addOptionsConfig(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('options')
                    ->info('options of the module')
                    ->children()
                        ->variableNode('enabled')
                            ->info('options enabled')
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    private function addDisruptionKey(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->variableNode('disruption_display_key')
                    ->info('clé de tri des messages de perturbations au niveau du type')
                    ->defaultValue('web')
                    ->end()
                ->end()
            ->end();
    }
}
