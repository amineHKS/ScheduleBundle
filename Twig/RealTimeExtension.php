<?php

namespace CanalTP\ScheduleBundle\Twig;

use Symfony\Component\Translation\TranslatorInterface;

/**
 * Description of RealTimeExtension
 *
 */
class RealTimeExtension extends \Twig_Extension
{

    private $translator;

    /**
     * RealTimeExtension constructor.
     * @param TranslatorInterface $translator3
     */
    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }
    
    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('next_departures_realtime', array($this, 'nextDeparturesRealTime'))
        );
    }

    /**
     * Display the waiting time when it's real time
     * @param type $realTime
     * @return string
     */
    public function nextDeparturesRealTime($realTime)
    {
        $currentDate = strtotime("now");
        $realtimeDate = strtotime($realTime);
        $result = round(abs($realtimeDate - $currentDate) / 60, 2);
        $numberOfMinutes = floor($result);

        if ($numberOfMinutes == 0) {
            $display = $this->translator->trans('next_departures.real_time.approach');
        } elseif ($numberOfMinutes <= 59) {
            $display = $numberOfMinutes . ' ' .$this->translator->trans('next_departures.real_time.minutes');
        } else {
            $display = null;
        }
        
        return $display;
    }

    public function getName()
    {
        return 'real_time_extension';
    }
}
