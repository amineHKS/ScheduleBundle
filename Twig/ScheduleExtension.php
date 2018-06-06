<?php

namespace CanalTP\ScheduleBundle\Twig;

use Symfony\Component\DependencyInjection\ContainerInterface;

class ScheduleExtension extends \Twig_Extension
{
    /**
     * @var ContainerInterface Service container
     */
    private $container;

    /**
     * Construteur de l'extension
     *
     * @param ContainerInterface $container Service container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('numericToAlphabeticKey', array($this, 'numericToAlphabeticKey')),
            new \Twig_SimpleFunction('numericToAlphabeticValue', array($this, 'numericToAlphabeticValue'))
        );
    }

    /**
     * Converts a the  array key from numeric to alphabetic key
     *
     * @param array $array
     * @return array
     */
    public function numericToAlphabeticKey($array)
    {
        if (count($array) > 0) {
            $kealphabetic = array_slice(range('a', 'z'), 0, count($array));
            return array_combine($kealphabetic, $array);
        } else {
            return $array;
        }
    }

    /**
     * Converts a numeric value into alphabetic value
     *
     * @param integer $value
     * @return alphabetic value
     */
    public function numericToAlphabeticValue($value)
    {
        $alphabeticArray = array();
        for ($i = 'a'; $i<='z'; $i++) {
            $alphabeticArray[] = $i;
        }
        return $alphabeticArray[$value];
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'schedule_extension';
    }
}
