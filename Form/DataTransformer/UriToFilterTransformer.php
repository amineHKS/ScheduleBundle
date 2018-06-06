<?php

namespace CanalTP\ScheduleBundle\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;

/**
 * This class is responsible of converting the given filter to an id: "stop_areas/stop_area:MY:ID" to "stop_area:MY:ID"
 * and the other way around, from "stop_area:MY:ID" to "stop_areas/stop_area:MY:ID".
 */
class UriToFilterTransformer implements DataTransformerInterface
{
    /**
     * Removes the collection name from the given filter (ie: stop_areas/stop_area:MY:STOP) to only keep
     * the place Id, so stop_areas/stop_area:MY:STOP becomes stop_area:MY:STOP
     *
     * @param  string $filter
     * @return String|null the place id in this filter
     */
    public function transform($filter)
    {
        if (is_null($filter)) {
            return null;
        }
        $parts = explode('/', $filter, 2); // will contains somethings like ['stop_areas','stop_area:MY:STOP']
        if (count($parts) === 1 || $parts[1] === '') {
            throw new \RuntimeException(sprintf(
                'Unable to guess a the place id from the "%s" filter, '.
                'checks that it follows the {collection_name}s/collection_name:ID syntax',
                $filter
            ));
        }

        return $parts[1];
    }

    /**
     * Adds the collection name to the given id, so stop_area:MY:STOP becomes stop_areas/stop_area:MY:STOP.
     * This method assumes that the given id follows the collection_name:ID syntax (ie: stop_area:MY:STOP)
     *
     * @param  string $placeId
     * @return String|null a path filter used to query Navitia, eg: "stop_areas/stop_area:MY:STOP"
     */
    public function reverseTransform($placeId)
    {
        if (is_null($placeId)) {
            return null;
        }
        $parts = explode(':', $placeId, 2);
        if (count($parts) === 1 || $parts[1] === '') {
            throw new \RuntimeException(sprintf(
                'Unable to guess a collection name from "%s" id, checks that it follows the collection_name:ID syntax',
                $placeId
            ));
        }
        // returns something like {stop_point}s/place:id
        return strtolower($parts[0].'s/').$placeId;
    }
}
