<?php
/**
 * @copyright   Copyright (C) 2014-2017 KnowledgeArc Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;

use \Joomla\Utilities\ArrayHelper;
use \Joomla\String\StringHelper;

JLoader::import('joomla.filesystem.folder');

/**
 * Ingest metadata using Joomla articles.
 *
 * @package     JHarvest.Plugin
 */
class PlgSystemArticle extends JPlugin
{
    protected $autoloadLanguage = true;

    public function onJHarvestIngest($items, $params)
    {
    jexit();
        $this->params->merge($params);

        if (!$this->params->get('user_id')) {
            throw new Exception(JText::_("PLG_SYSTEM_ARTICLE_NO_USER"));
        }

        $user = \JFactory::getUser($this->params->get('user_id'));
        \JFactory::getSession()->set('user', $user);

        // A general test as to whether the Article Manager allows the current
        // user to edit custom field values.
        if (!$user->authorise('core.edit.value', "com_content")) {
            throw new Exception(JText::_("PLG_SYSTEM_ARTICLE_EDIT_FIELD_VALUES_NOT_ALLOWED"));
        }

        JModelLegacy::addIncludePath(JPATH_ADMINISTRATOR.'/components/com_content/models', 'ContentModel');

        $languages = \JLanguageHelper::getContentLanguages();

        foreach ($items as $item) {
            $itemData = json_decode($item->data);

            $metadata = $itemData->metadata;
            $assets = $itemData->assets;

            $article = JModelLegacy::getInstance("Article", "ContentModel", ["ignore_request"=>true]);

            $data = [];

            if (isset($metadata->title) && !is_null($metadata->title)) {
                $data["title"] = array_shift($metadata->title);
            } else {
                $data["title"] = JText::_("PLG_SYSTEM_ARTICLE_UNDEFINED");
            }

            if (isset($metadata->description) && !is_null($metadata->description)) {
                $data["description"] = array_shift($metadata->description);
            }

            if (isset($metadata->language) && !is_null($metadata->language)) {
                $found = false;
                $language = array_shift($metadata->language);

                reset($languages);

                while (!$found && $lang = current($languages)) {
                    $code = $lang->lang_code;

                    $match = ($code == $language);
                    $nearMatch = (strlen($language) == 2 && strpos($code, $language) === 0);

                    if ($match || $nearMatch) {
                        $found = $lang->lang_code;
                    }

                    next($languages);
                }

                if ($found) {
                    $data["language"] = $found;
                } else {
                    $data["language"] = "*";
                }
            } else {
                $data["language"] = "*";
            }

            $data["catid"] = $this->params->get('ingest.article.catid');
            $data["alias"] = null;

            foreach (ArrayHelper::fromObject($metadata) as $key=>$value) {
                $i = 0;

                $this->createField($key);

                foreach ($value as $v) {
                    $data["com_fields"][$key]["$key".$i] = $v;
                    $i++;
                }
            }

            $data["plg_content_assets"] = ["asset"];

            foreach ($assets as $asset) {
                $asset->link = $asset->url;
                unset($asset->url);

                $data["plg_content_assets"]["asset"][] = ArrayHelper::fromObject($asset);
            }

            // trick com_content into generating new aliases and handling duplicate
            // titles.
            JFactory::getApplication()->input->set("task", "save");

            if ($article->save($data)) {
                JTable::addIncludePath(__DIR__."/tables");
                $ingestedArticle = JTable::getInstance("IngestArticle", "ContentTable");

                $ingestedArticle->bind(
                    [
                        "content_id"=>$article->getItem()->id,
                        "item_id"=>$item->id
                    ]
                );

                if ($ingestedArticle->store()) {
                    $this->_subject->setError($article->getError());
                }
            } else {
                $this->_subject->setError($article->getError());
            }
        }
    }

    private function createField($fieldName)
    {
        $ignoreFields = ["title", "description", "language"];

        if ((bool)$this->params->get('autocreate_fields', 1)) {
            JModelLegacy::addIncludePath(
                JPATH_ROOT.'/administrator/components/com_fields/models',
                'FieldsModel');

            $model = \JModelLegacy::getInstance("Fields", "FieldsModel", ["ignore_request"=>true]);
            $model->setState("filter.name", $fieldName);
            $model->setState("filter.context", "com_content.article");

            $fields = $model->getItems();

            $found = false;

            while (($field = current($fields)) && !$found) {
                $found = (StringHelper::strtolower($fieldName) == $field->name);

                next($fields);
            }

            if (!$found && array_search($fieldName, $ignoreFields) === false) {
                $model = \JModelLegacy::getInstance(
                    'Field',
                    'FieldsModel',
                    ["ignore_request"=>true]);

                $data =
                [
                    "name"=>$fieldName,
                    "title"=>StringHelper::ucfirst($fieldName),
                    "label"=>StringHelper::ucfirst($fieldName),
                    "assigned_cat_ids"=>$this->params->get('autocreate_field_cat_ids'),
                    "group_id"=>$this->params->get('autocreate_field_group_id'),
                    "type"=>"metadata",
                    "state"=>1,
                    "context"=>"com_content.article"
                ];

                if (!$model->save($data)) {
                    JHarvestHelper::log($fieldName." ".$model->getError(), JLog::ERROR);
                }
            }
        }
    }

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

        if (!in_array($name, ['com_jharvest.harvest'])) {
            return true;
        }

        JForm::addFormPath(__DIR__.'/forms');
        $form->loadFile('params', false);

        return true;
    }

    public function onContentBeforeDelete($context, $item)
    {
        if (empty($item->id)) {
            return true;
        }

        if (!in_array($context, ['com_content.article'])) {
            return true;
        }

        try {
            JTable::addIncludePath(__DIR__."/tables");
            $table = JTable::getInstance('IngestArticle', 'ContentTable');

            if (!$table->delete((int)$item->id)) {
                throw new Exception($table->getError());
            }
        } catch (Exception $e) {
            $this->_subject->setError($e->getMessage());
            return false;
        }

        return true;
    }
}
