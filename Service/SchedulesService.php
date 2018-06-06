<?php

namespace CanalTP\ScheduleBundle\Service;

use CanalTP\PlacesBundle\Service\AbstractCoverageService;

/**
 * Classe commune pour certaines fonctions utilisées dans les gilles horaire
 *
 * @author pthegner
 */
class SchedulesService extends AbstractCoverageService
{
    /**
     * Fonction permettant de récupérer la partie notes
     * @param array $linksArray Tableau contenant le json de type "links"
     * @param array $notesKeyList Tableau comprenant une liste des identifiants ("id")
     * @param array $notesValueList Tableau comprenant une liste des valeurs ("value")
     * @param array $notesList Tableau reprenant la partie "notes" du json
     * @return array Tableau réecrivant la partie notes
     */
    public function getArrayNotes($linksArray, &$notesKeyList, &$notesValueList, $notesList)
    {
        $notes = array();
        foreach ($linksArray as $links) {
            if ($links->type == 'notes') {
                $j = array_search($links->id, $notesKeyList);
                if ($j === false) {
                    $j = count($notesKeyList);
                    $notesKeyList[] = $links->id;
                    $notesValueList[] = $notesList[$links->id];
                }
                $notes[] = $j;
            }
        }
        return $notes;
    }
}
