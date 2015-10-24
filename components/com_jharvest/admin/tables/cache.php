<?php
/**
 * @copyright   Copyright (C) 2015 KnowledgeArc Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
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
