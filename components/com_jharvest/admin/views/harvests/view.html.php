<?php
/**
 * @package     JHarvest.Component
 * @subpackage  View
 * @copyright   Copyright (C) 2014-2017 KnowledgeArc Ltd. All rights reserved.
 * @license     This file is part of the JHarvest component for Joomla!.
 */

defined('_JEXEC') or die;

/**
 * Provides a view for displaying and managing multiple JHarvest harvests.
 *
 * @package     JHarvest.Component
 * @subpackage  View
 */
class JHarvestViewHarvests extends JViewLegacy
{
    protected $items;

    protected $pagination;

    protected $state;

    protected $option;

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     *
     * @return  void
     */
    public function display($tpl = null)
    {
        $this->option = JFactory::getApplication()->input->getCmd('option');
        $this->items         = $this->get('Items');
        $this->pagination    = $this->get('Pagination');
        $this->state         = $this->get('State');
        $this->authors       = $this->get('Authors');
        $this->activeFilters = $this->get('ActiveFilters');

        // Check for errors.
        if (count($errors = $this->get('Errors'))) {
            JError::raiseError(500, implode("\n", $errors));

            return false;
        }

        $this->addToolbar();
        $this->sidebar = JHtmlSidebar::render();

        parent::display($tpl);
    }

    protected function addToolbar()
    {
        $canDo = JHarvestHelper::getActions('com_jharvest');
        $user  = JFactory::getUser();

        // Get the toolbar object instance
        $bar = JToolBar::getInstance('toolbar');

        JToolbarHelper::title(JText::_('COM_JHARVEST_HARVESTS_TITLE'), 'stack article');

        if ($canDo->get('core.create')) {
            JToolbarHelper::addNew('harvest.add');
        }

        if (($canDo->get('core.edit')) || ($canDo->get('core.edit.own'))) {
            JToolbarHelper::editList('harvest.edit');
        }

        if ($canDo->get('core.edit.state')) {
            JToolbarHelper::publish('harvests.publish', 'JTOOLBAR_PUBLISH', true);
            JToolbarHelper::unpublish('harvests.unpublish', 'JTOOLBAR_UNPUBLISH', true);
            JToolbarHelper::checkin('harvests.checkin');
        }

        if ($this->state->get('filter.state') == -2 && $canDo->get('core.delete')) {
            JToolbarHelper::deleteList('', 'harvests.delete', 'JTOOLBAR_EMPTY_TRASH');
        }
        elseif ($canDo->get('core.edit.state')) {
            JToolbarHelper::custom(
                'harvests.reset',
                'refresh',
                'refresh',
                'COM_JHARVEST_HARVESTS_BUTTON_RESET',
                false);

            JToolbarHelper::trash('harvests.trash');
        }

        if ($user->authorise('core.admin', $this->option)) {
            JToolbarHelper::preferences($this->option);
        }

        JToolbarHelper::help('JHELP_JHARVEST_HARVESTS_MANAGER');

        JHtmlSidebar::setAction('index.php?option=com_jharvest&view=harvests');

        JHtmlSidebar::addFilter(
            JText::_('JOPTION_SELECT_PUBLISHED'),
            'filter_state',
            JHtml::_('select.options', JHtml::_('jgrid.publishedOptions'), 'value', 'text', $this->state->get('filter.state'), true)
        );
    }
}
