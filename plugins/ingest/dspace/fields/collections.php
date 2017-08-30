<?php
/**
 * @package     Ingest.DSpace.Plugin
 * @subpackage  Form
 *
 * @copyright   Copyright (C) 2015-2017 KnowledgeArc Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('JPATH_BASE') or die;

JLoader::import('joomla.form.helper');
JFormHelper::loadFieldClass('list');

/**
 * @package     Ingest.DSpace.Plugin
 * @subpackage  Form
 *
 * A class for generating a DSpace collections drop down.
 */
class IngestDSpaceFormFieldCollections extends \JFormFieldList
{
    protected $type = 'IngestDSpace.Collections';

    /**
     * Uses the configured DSpace Collections API endpoint to retrieve a list
     * of DSpace collections.
     */
    protected function getOptions()
    {
        // Initialize variables.
        $options = parent::getOptions();

        $http = JHttpFactory::getHttp();

        $plugin = JPluginHelper::getPlugin("ingest", "dspace");
        $params = new JRegistry($plugin->params);

        $headers = array(
            'user'=>$params->get('username'),
            'pass'=>$params->get('password'));

        $url = new JUri($params->get('rest_url').'/collections.json');

        try {
            $response = $http->get((string)$url, $headers);

            if ($response->code === 200) {
                $data = json_decode($response->body);

                foreach ($data->collections as $collection) {
                    $options[$collection->id] = $collection->name;
                }
            }
        } catch (Exception $e) {
            JLog::add($e->getMessage(), JLog::ERROR, 'jerror');
        }

        return $options;
    }
}
