<?php
/**
 * @copyright   Copyright (C) 2015 KnowledgeArc Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;

JLoader::import('joomla.filesystem.folder');

/**
 * Ingests metadata and assets into DSpace.
 *
 * @package     JHarvest.Plugin
 */
class PlgIngestDSpace extends JPlugin
{
    protected $autoloadLanguage = true;

    public function __construct($subject, $config = array())
    {
        parent::__construct($subject, $config);

        \JLog::addLogger(array());
    }

    /**
     * Gets the cached records belonging to this harvest.
     *
     * The cache can be returned in chunks to avoid performance issues.
     *
     * @param   int        $start  The cache offset.
     * @param   int        $limit  The size of the cache to return.
     *
     * @return  JObject[]  An array of cached records.
     */
    public function getCache($start = 0, $limit = 100)
    {
        $database = \JFactory::getDbo();

        $query = $database->getQuery(true);

        $select = array(
            $database->qn('id'),
            $database->qn('harvest_id'),
            $database->qn('data'));

        $query
            ->select($select)
            ->from($database->qn('#__jharvest_cache', 'cache'))
            ->where($database->qn('cache.harvest_id').'='.(int)$this->harvestId);

        $database->setQuery($query, $start, $limit);

        return $database->loadObjectList('id');
    }

    public function onJHarvestIngest($harvest)
    {
        $params = new JRegistry($harvest->params);

        $this->harvestId = $harvest->id;

        $items = $this->getCache(0);

        $i = count($items);

        $temp = 0;

        while (count($items) > 0) {
            foreach ($items as $item) {
                $data = json_decode($item->data);

                $metadata = $data->metadata;
                $assets = $data->assets;

                if (!isset($metadata->{"dc.type"})) {
                    $metadata->{"dc.type"} = array("-");
                }

                if (!isset($metadata->{"dc.description"})) {
                    $metadata->{"dc.description"} = array("-");
                }

                $collection = $params->get('ingest.dspace.collection');

                $path = $this->buildPackage($item->id, $collection, $metadata, $assets);

                $http = JHttpFactory::getHttp(null, 'curl');

                $headers = array(
                    'user'=>$this->params->get('username'),
                    'pass'=>$this->params->get('password'),
                    'Content-Type'=>'multipart/form-data');

                $post = array(
                    'upload'=>
                        curl_file_create($path, 'application/zip', JFile::getName($path)));

                $url = new JUri($this->params->get('rest_url').'/items.stream');
                $response = $http->post($url, $post, $headers);

                if ($response->code == '201') {
                    JLog::add(print_r($response, true), JLog::DEBUG, 'ingestdspace');
                } else {
                    JLog::add(print_r($response, true), JLog::ERROR, 'ingestdspace');
                }

                JFile::delete($path);
            }

            $items = $this->getCache($i);
            $i+=count($items);
        }
    }

    /**
     * Build a DSpace-compatible package.
     *
     * @param   string  $id          The handle of the package.
     * @param   int     $collection  The collection to add the package to.
     * @param   array   $metadata    An array of metadata describing the package.
     * @param   array   $assets      An array of assets to package.
     *
     * @return  string  The path to the zipped package.
     */
    private function buildPackage($id, $collection, $metadata, $assets)
    {
        $name = JFile::makeSafe($id);
        $path = JPATH_ROOT.'/tmp/'.$name;
        $zip = $path.'.zip';

        JFolder::create($path);

        $request = new SimpleXMLElement("<request/>");
        $request->collectionId = new SimpleXMLElement("<collectionId>".(int)$collection."</collectionId>");
        $request->metadata = new SimpleXMLElement("<metadata/>");

        $i = 0;
        foreach ($metadata as $key=>$field) {
            $element = $request->metadata->addChild("field");

            foreach ($field as $value) {
                $element->name = $key;
                $element->value = $value;
                $i++;
            }
        }

        $bundle = $request->addChild("bundles")->addChild("bundle");

        $bundle->addChild("name", "ORIGINAL");
        $bitstreams = $bundle->addChild("bitstreams");

        $files = array();

        foreach ($assets as $asset) {
            $bitstream = $bitstreams->addChild("bitstream");
            $bitstream->addChild("name", htmlspecialchars($asset->name));
            $bitstream->addChild("mimeType", $asset->type);

            $url = JUri::getInstance($asset->url);
            $parts = explode('/', $url->getPath());
            $url->setPath(join('/', array_map('rawurlencode', $parts)));

            $src = (string)$url;
            $dest = $path.'/'.JFile::makeSafe($asset->name);

            JLog::add("Asset Src: ".$src, JLog::DEBUG, 'ingestdspace');
            JLog::add("Asset Dst: ".$dest, JLog::DEBUG, 'ingestdspace');

            $this->download($src, $dest);

            $handle = fopen($dest, "r");

            $files[] = array(
                "name"=>JFile::makeSafe($asset->name),
                "data"=>fread($handle, $asset->size));

            fclose($handle);
        }

        $files[] = array('name'=>'package.xml', 'data'=>$request->saveXML());

        $package = JArchive::getAdapter('zip');
        $package->create($zip, $files);

        JFolder::delete($path);

        return $zip;
    }

    /**
     * Downloads a file to a temporary location.
     *
     * @param  string   $src   The url to download from.
     * @param  string   $dest  The location to download to.
     */
    private function download($src, $dest)
    {
        if ($shandle = @fopen($src, 'r'))
        {
            $dhandle = fopen($dest, 'w');

            while (!feof($shandle))
            {
                $chunk = fread($shandle, 1024);
                fwrite($dhandle, $chunk);
            }

            fclose($dhandle);
            fclose($shandle);
        }
    }
}
