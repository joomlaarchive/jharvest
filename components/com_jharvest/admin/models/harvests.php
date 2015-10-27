<?php
/**
 * @copyright   Copyright (C) 2015 KnowledgeArc Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;

/**
 * Models the display and management of multiple harvests.
 *
 * @package     JHarvest.Component
 * @subpackage  Model
 */
class JHarvestModelHarvests extends JModelList
{
    public function __construct($config = array())
    {
        if (empty($config['filter_fields']))
        {
            $config['filter_fields'] = array(
                'id', 'h.id',
                'state', 'h.state',
                'h.harvested'
            );
        }

        parent::__construct($config);
    }

    protected function populateState($ordering = null, $direction = null)
    {
        $published = $this->getUserStateFromRequest($this->context . '.filter.state', 'filter_state', '', 'string');
        $this->setState('filter.state', $published);

        // Load the parameters.
        $params = JComponentHelper::getParams('com_jharvest');
        $this->setState('params', $params);
    }

    public function getItems()
    {
        $items = parent::getItems();

        for ($i=0; $i<count($items); $i++) {
            // format the dates using the localized format.
            $items[$i]->created = JHtml::_('date', $items[$i]->created, JText::_('DATE_FORMAT_LC4'));

            if ($items[$i]->harvested == JFactory::getDbo()->getNullDate()) {
                $items[$i]->harvested = JText::_('COM_JHARVEST_HARVESTS_HARVESTED_NEVER');
            } else {
                $items[$i]->harvested = JHtml::_('date', $items[$i]->harvested, JText::_('DATE_FORMAT_LC4'));
            }

            $params = new JRegistry;
            $params->loadString($items[$i]->params);
            $items[$i]->params = $params;
        }

        return $items;
    }

    protected function getListQuery()
    {
        $db = $this->getDbo();
        $query = $db->getQuery(true);
        $user = JFactory::getUser();

        $table = $this->getTable('Harvest', 'JHarvestTable');
        $fields = array();

        foreach ($table->getFields() as $field) {
            $fields[] = 'h.'.$db->qn($field->Field);
        }

        $query->select($this->getState('list.select', $fields));

        $query
            ->from('#__jharvest_harvests AS h');

        // Join over the users for the checked out user.
        $query->select('uc.name AS editor')
        ->join('LEFT', '#__users AS uc ON uc.id=h.checked_out');

        // Join over the users for the author.
        $query->select('ua.name AS author_name')
        ->join('LEFT', '#__users AS ua ON ua.id = h.created_by');

        // Filter by search in title.
        $search = $this->getState('filter.search');

        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $query->where('h.id = ' . (int) substr($search, 3));
            } elseif (stripos($search, 'author:') === 0) {
                $search = $db->quote('%' . $db->escape(substr($search, 7), true) . '%');
                $query->where('(ua.name LIKE ' . $search . ' OR ua.username LIKE ' . $search . ')');
            } else {
                $search = $db->quote('%' . $db->escape($search, true) . '%');
                $query->where('(h.originating_url LIKE ' . $search . ' OR h.params LIKE ' . $search . ')');
            }
        }

        // Filter by published state
        $state = $this->getState('filter.state');

        if (is_numeric($state)) {
            $query->where('h.state = ' . (int)$state);
        } elseif ($state === '') {
            $query->where('(h.state=0 OR h.state=1)');
        }

        return $query;
    }
}
