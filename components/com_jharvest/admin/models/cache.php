<?php
class JHarvestModelCache extends \JModelList
{
    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @see     JControllerLegacy
     */
    public function __construct($config = array())
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'harvest_id', 'a.harvest_id'
            ];
        }

        parent::__construct($config);
    }

    /**
     * Gets a list of items from the cache.
     */
    protected function getListQuery()
    {
        $db = \JFactory::getDbo();

        $query = $db->getQuery(true);

        $select = $db->qn(['a.id', 'a.harvest_id', 'a.data', 'a.state']);

        $query
            ->select($select)
            ->from($db->qn('#__jharvest_cache', 'a'));

        if ($harvestId = $this->getState('filter.harvest_id')) {
            $query->where($db->qn('a.harvest_id').'='.$harvestId);
        }

        if (is_numeric($state = $this->getState('filter.state'))) {
            $query->where($db->qn('a.state').' = '.$state);
        }

        return $query;
    }

    protected function getStoreId($id = '')
    {
        // Compile the store id.
        $id .= ':' . $this->getState('filter.harvest_id');

        return parent::getStoreId($id);
    }
}
