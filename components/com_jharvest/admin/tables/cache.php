<?php
/**
 * @copyright   Copyright (C) 2014-2017 KnowledgeArc Ltd. All rights reserved.
 * @license     This file is part of the JHarvest component for Joomla!.
 */

defined('_JEXEC') or die;

/**
 * Represents a JHarvest cache item.
 *
 * @package     JHarvest
 * @subpackage  Table
 */
class JHarvestTableCache extends JTable
{
    /**
     * Instantiates an instance of the JHarvestTableCache table.
     *
     * @param  JDatabaseDriver  $db  Database connector object.
     */
    public function __construct(&$db)
    {
        parent::__construct('#__jharvest_cache', 'id', $db);
        $this->_autoincrement = false;
    }
}
