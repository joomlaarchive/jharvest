<?php
/**
 * A script for intercepting calls to this component and handling them appropriately.
 *
 * @copyright   Copyright (C) 2015 KnowledgeArc Ltd. All rights reserved.
 * @license     This file is part of the JHarvest component for Joomla!.
 */
defined('_JEXEC') or die;

JHtml::_('behavior.tabstate');

if (!JFactory::getUser()->authorise('core.manage', 'com_jharvest'))
{
    throw new Exception(JText::_('JERROR_ALERTNOAUTHOR'), 404);
}

JLoader::register('JHarvestHelper', __DIR__ . '/helpers/jharvest.php');
//JLoader::registerNamespace('JSpace', JPATH_PLATFORM);

$controller = JControllerLegacy::getInstance('JHarvest');
$controller->execute(JFactory::getApplication()->input->get('task'));
$controller->redirect();
