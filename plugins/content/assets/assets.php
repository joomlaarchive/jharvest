<?php
/**
 * @copyright   Copyright (C) 2014-2017 KnowledgeArc Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */
defined('_JEXEC') or die;

use \Joomla\Utilities\ArrayHelper;

/**
 * Asset Links plugin.
 */
class PlgContentAssets extends JPlugin
{
    protected $autoloadLanguage = true;

    /**
     * Add the assets form field to the article form.
     *
     * @param   JForm  $form
     * @param   array  $data
     *
     * @return  bool   True if the additional form fields are loaded correctly,
     * false otherwise.
     */
    public function onContentPrepareForm($form, $data)
    {
        if (!($form instanceof JForm)) {
            $this->_subject->setError('JERROR_NOT_A_FORM');

            return false;
        }

        // Check we are manipulating a valid form.
        $name = $form->getName();

        if (!in_array($name, ['com_content.article'])) {
            return true;
        }

        if (!$this->isInAssignedCategories($data->catid)) {
            return true;
        }

        JForm::addFormPath(__DIR__.'/forms');
        $form->loadFile('assets', false);

        return true;
    }

    /**
     * Load the assets data, adding it to the existing article data.
     *
     * @param   string  $context
     * @param   array   $data
     *
     * @return  bool    True if the data is loaded successfully, false otherwise.
     */
    public function onContentPrepareData($context, $data)
    {
        if (!in_array($context, ['com_content.article'])) {
            return true;
        }

        if (!$this->isInAssignedCategories($data->catid)) {
            return true;
        }

        if (is_object($data)) {
            $db = JFactory::getDbo();
            $query = $db->getQuery(true);

            $query
                ->select("id, link, description, ordering")
                ->from("#__content_assets")
                ->where("content_id=".$data->id)
                ->order("ordering");

            $db->setQuery($query);

            try {
                $data->plg_content_assets = ["asset"=>$db->loadObjectList()];
            } catch (RuntimeException $e) {
                $this->_subject->setError($e->getMessage());

                return false;
            }
        }

        return true;
    }

    /**
     * Saves assets to the content_assets table after the article has been saved.
     *
     * @param   string               $context
     * @param   ContentTableContent  $item
     * @param   bool                 $isNew
     * @param   array                $data
     *
     * @return  bool                 True on successful save, false otherwise.
     */
    public function onContentAfterSave($context, $item, $isNew, $data = array())
    {
        if (!is_array($data) || empty($item->id)) {
            return true;
        }

        if (!in_array($context, ['com_content.article'])) {
            return true;
        }

        if (!isset($data["plg_content_assets"]["asset"])) {
            return true;
        }

        $i = 1;

        foreach ($data["plg_content_assets"]["asset"] as $asset) {
            $asset["content_id"] = $item->id;
            $asset["ordering"] = $i;

            JTable::addIncludePath(__DIR__."/tables");
            $table = JTable::getInstance('Asset', 'ContentTable');
            $table->bind($asset);

            if (!$table->store()) {
                $this->_subject->setError($table->getError());

                return false;
            }

            $i++;
        }

        return true;
    }

    public function onContentAfterDelete($context, $item)
    {
        if (empty($item->id)) {
            return true;
        }

        if (!in_array($context, ['com_content.article'])) {
            return true;
        }

        $db = JFactory::getDbo();
        $query = $db->getQuery(true);

        $query
            ->select("id")
            ->from("#__content_assets")
            ->where("content_id=".$item->id);

        $db->setQuery($query);

        $rows = $db->loadColumn();

        try {
            JTable::addIncludePath(__DIR__."/tables");
            $table = JTable::getInstance('Asset', 'ContentTable');

            foreach ($rows as $row) {
                if (!$table->delete((int)$row)) {
                    throw new Exception($table->getError());
                }
            }
        } catch (Exception $e) {
            $this->_subject->setError($e->getMessage());
            return false;
        }

        return true;
    }

    public function onContentPrepare($context, $item)
    {
        if (in_array($context, ['com_content.article'])) {
            if (!$this->isInAssignedCategories($item->catid)) {
                return;
            }

            $db = JFactory::getDbo();
            $query = $db->getQuery(true);

            $query
                ->select("id, link, description, ordering")
                ->from("#__content_assets")
                ->where("content_id=".$item->id)
                ->order("ordering");

            $db->setQuery($query);

            $item->plg_content_assets = ["asset"=>$db->loadObjectList()];
        }
    }

    public function onContentAfterDisplay($context, $item, $params, $limitstart = 0)
    {
        if (in_array($context, ['com_content.article']) &&
            isset($item->plg_content_assets)) {
            $basePath =  __DIR__.'/layouts';

            $layout = new JLayoutFile('content.assets.default', $basePath);

            return $layout->render($item->plg_content_assets);
        }
    }

    private function isInAssignedCategories($cid)
    {
        $cids = $this->params->get("catids");

        if (count($cids) == 1 && ArrayHelper::getValue($cids, 0) == "") {
            return true;
        }

        if (array_search($cid, $cids) !== false) {
            return true;
        }

        return false;
    }
}
