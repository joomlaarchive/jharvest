<?php
/**
 * @copyright   Copyright (C) 2014-2017 KnowledgeArc Ltd. All rights reserved.
 * @license     This file is part of the JHarvest component for Joomla!.
 */

defined('_JEXEC') or die;

use Joomla\Registry\Registry;

class JHarvestTableHarvest extends JTable
{
    /**
     * Instantiates an instance of the JHarvestTableHarvest table.
     *
     * @param  JDatabaseDriver  $db  Database connector object.
     */
    public function __construct(&$db)
    {
        parent::__construct('#__jharvest_harvests', 'id', $db);

        $this->setColumnAlias('published', 'state');
    }

    /**
     * (non-PHPdoc)
     */
    public function bind($array, $ignore = '')
    {
        if (isset($array['params']) && is_array($array['params'])) {
            $registry = new Registry;
            $registry->loadArray($array['params']);
            $array['params'] = (string)$registry;
        }

        return parent::bind($array, $ignore);
    }

    /**
     * (non-PHPdoc)
     */
    public function store($updateNulls = false)
    {
        $date = JFactory::getDate();
        $user = JFactory::getUser();

        if ($this->id) {
            // Existing item
            $this->modified = $date->toSql();
            $this->modified_by = $user->get('id');
        } else {
            if (!(int) $this->created) {
                $this->created = $date->toSql();
            }

            if (empty($this->created_by)) {
                $this->created_by = $user->get('id');
            }
        }

        return parent::store($updateNulls);
    }
}
