<?php
/**
 * @copyright   Copyright (C) 2014 KnowledgeArc Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;

/**
 * Models the management of a harvest.
 *
 * @package     JHarvest.Component
 * @subpackage  Model
 */
class JHarvestModelHarvest extends JModelAdmin
{
    protected $typeAlias;

    public function __construct($config = array())
    {
        parent::__construct($config);

        $this->typeAlias = $this->get('option').'.'.$this->getName();
    }

    public function getItem($pk = null)
    {
        $app = JFactory::getApplication();
        $item = parent::getItem($pk);

        // Override the base user data with any data in the session.
        $data = $app->getUserState('com_jharvest.edit.harvest.data', array());

        foreach ($data as $k => $v) {
            $item->$k = $v;
        }

        // provide a quick way to detect if discovery has occurred.
        $discovery = JArrayHelper::getValue($item->params, 'discovery');
        if (JArrayHelper::getValue($discovery, 'url')) {
            $item->discovered = true;
        } else {
            $item->discovered = false;
        }

        $dispatcher = JEventDispatcher::getInstance();
        JPluginHelper::importPlugin('content');

        // Trigger the data preparation event.
        $dispatcher->trigger('onContentPrepareData', array($this->typeAlias, $item));

        return $item;
    }

    public function getForm($data = array(), $loadData = true)
    {
        $form = $this->loadForm($this->typeAlias, $this->getName(), array('control'=>'jform', 'load_data'=>$loadData));

        if (empty($form)) {
            return false;
        }

        return $form;
    }

    protected function loadFormData()
    {
        $app = JFactory::getApplication();

        $data = $this->getItem();

        $this->preprocessData($this->typeAlias, $data);

        return $data;
    }

    protected function preprocessForm(\JForm $form, $data, $group = 'content')
    {
        // if no data, grab the posted form data.
        if (!$data instanceof JObject) {
            $data = JFactory::getApplication()->input->get('jform', $data, 'array');
            $data = JArrayHelper::toObject($data);
        }

        $params = new JRegistry;
        $params->loadArray($data->params);

        if ($params->get('discovery.url')) {
            $plugin = $params->get('discovery.type');

            $language = JFactory::getLanguage();
            $language->load('plg_jharvest_'.$plugin);

            $path = JPATH_ROOT.'/plugins/jharvest/'.$plugin.'/forms/harvest.xml';
            $form->loadFile($path, false);

            foreach (JPluginHelper::getPlugin('jharvest') as $plugin) {
                $path = JPATH_ROOT.'/plugins/jharvest/'.$plugin->name.'/forms/ingest.xml';
                $form->loadFile($path, false);
            }

            $form->removeField('originating_url');
            $form->removeField('harvester');

            // hide the run_once value (users cannot set it after discovery)
            $form->setFieldAttribute("run_once", 'type', 'hidden');
            $form->setFieldAttribute("run_once", 'class', '');
        } else {
            $form->removeField('state');
        }

        parent::preprocessForm($form, $data, $group);
    }

    public function getTable($type = 'Harvest', $prefix = 'JHarvestTable', $config = array())
    {
        return parent::getTable($type, $prefix, $config);
    }

    public function save($data)
    {
        $pk = JArrayHelper::getValue($data, 'id', (int)$this->getState('harvest.id'));

        try {
            $table = $this->getTable();

            if ($pk > 0) {
                $table->load($pk);
                $isNew = false;
            }

            if (!$table->bind($data)) {
                $this->setError($table->getError());
                return false;
            }

            if (!$table->check()) {
                $this->setError($table->getError());
                return false;
            }

            if (!$table->store()) {
                $this->setError($table->getError());
                return false;
            }
        } catch (Exception $e) {
            JLog::addLogger(array());
            JLog::add($e->getMessage(), JLog::ERROR, 'jharvest');
            $this->setError(JText::_('JERROR_AN_ERROR_HAS_OCCURRED'));
            return false;
        }

        $this->setState('harvest.id', $harvest->id);

        $this->setState($this->getName() . '.new', ($pk ? false : true));

        return true;
    }

    public function discover($data)
    {
        $discovered = false;

        $pk = (int)$this->getState('harvest.id');
        $pk = JArrayHelper::getValue($data, 'id', $pk);

        // discover cannot be run on an existing harvest.
        if ($pk) {
            throw new Exception(JText::_("Discover cannot be run on an existing harvest.", 500));
        }

        $url = JArrayHelper::getValue($data, 'originating_url');

        $dispatcher = JEventDispatcher::getInstance();
        JPluginHelper::importPlugin('jharvest');

        try {
            $result = $dispatcher->trigger('onJHarvestDiscover', array($url));

            foreach ($result as $item) {
                if ($item) {
                    $discovered = $item;
                    break;
                }
            }

            if ($discovered) {
                $data['params'] = $discovered->toArray();

                JFactory::getApplication()->setUserState('com_jharvest.edit.harvest.data', $data);
                return true;
            }
        } catch (Exception $e) {
            JLog::addLogger(array());
            JLog::add($e->getMessage(), JLog::ERROR, 'jharvest');
            $this->setError(JText::_('JERROR_AN_ERROR_HAS_OCCURRED'));
        }

        return false;
    }
}
