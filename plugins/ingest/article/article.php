<?php
/**
 * @copyright   Copyright (C) 2014-2017 KnowledgeArc Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;

JLoader::import('joomla.filesystem.folder');

/**
 * Ingest metadata using Joomla articles.
 *
 * @package     JHarvest.Plugin
 */
class PlgIngestArticle extends JPlugin
{
    protected $autoloadLanguage = true;

    public function __construct($subject, $config = array())
    {
        parent::__construct($subject, $config);

        \JLog::addLogger(array());
    }

    public function onJHarvestIngest($items, $params)
    {
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
                $data["title"] = "Undefined";
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

            $data["catid"] = $params->get('ingest.article.catid');

            $article->save($data);
        }
    }
}
