<?php
/**
 * The JHarvest helper.
 *
 * @copyright   Copyright (C) 2015 KnowledgeArc Ltd. All rights reserved.
 * @license     This file is part of the JHarvest component for Joomla!.
 */

defined('_JEXEC') or die('Restricted access');

class JHarvestHelper extends JHelperContent
{
    public static function publishedOptions()
    {
        // Build the active state filter options.
        $options   = array();
        $options[] = JHtml::_('select.option', '*', 'JALL');
        $options[] = JHtml::_('select.option', '1', 'JENABLED');
        $options[] = JHtml::_('select.option', '0', 'JDISABLED');
        $options[] = JHtml::_('select.option', '2', 'JARCHIVED');
        $options[] = JHtml::_('select.option', '-2', 'JTRASHED');

        return $options;
    }

    public static function clearCache()
    {
        $db = JFactory::getDbo();
        $db->truncateTable("#__jharvest_cache");
    }
}
