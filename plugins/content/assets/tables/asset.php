<?php
/**
 * @package     JCar.Component
 * @subpackage  Administrator
 *
 * @copyright   Copyright (C) 2015-2017 KnowledgeArc Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die;

/**
 * Asset Table class.
 */
class ContentTableAsset extends JTable
{
    /**
     * Constructor
     *
     * @param   JDatabaseDriver  &$db  Database connector object
     */
    public function __construct(&$db)
    {
        parent::__construct('#__content_assets', 'id', $db);
    }

    /**
     * Overloaded check function
     *
     * @return  boolean  True on success, false on failure
     *
     * @see     JTable::check
     */
    public function check()
    {
        // Check for valid title
        if (trim($this->title) == '')
        {
            $this->setError(JText::_('PLG_CONTENT_ASSET_WARNING_PROVIDE_VALID_TITLE'));

            return false;
        }

        // Verify that the title is unique
        $table = JTable::getInstance('Asset', 'ContentTable');

        if ($table->load(array('title'=>$this->title)) && ($table->id != $this->id || $this->id == 0)) {
            $this->setError(JText::_('PLG_CONTENT_ASSET_ERROR_UNIQUE_TITLE'));

            return false;
        }

        return true;
    }
}
