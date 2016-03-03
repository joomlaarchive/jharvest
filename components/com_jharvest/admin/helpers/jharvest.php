<?php
/**
 * The JHarvest helper.
 *
 * @copyright   Copyright (C) 2015 KnowledgeArc Ltd. All rights reserved.
 * @license     This file is part of the JHarvest component for Joomla!.
 */

defined('_JEXEC') or die('Restricted access');

use \Joomla\Utilities\ArrayHelper;

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

    public static function log($msg, $type = JLog::ERROR)
    {
        $app = ArrayHelper::getValue($GLOBALS, 'application');

        $verbose = (bool)($app->input->get('v') || $app->input->get('verbose'));
        $quiet = (bool)($app->input->get('q') || $app->input->get('quiet'));

        if (get_class($app) == "JHarvestCli") {
            if ($type == JLog::ERROR) {
                if (!$quiet) {
                    $app->out($msg);
                }
            } else {
                if ($verbose) {
                    $app->out($msg);
                }
            }
        } else {
            JLog::add($msg, $type, 'jharvest');
        }
    }
}
