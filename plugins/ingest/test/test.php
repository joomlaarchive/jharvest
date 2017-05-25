<?php
/**
 * @copyright   Copyright (C) 2014-2017 KnowledgeArc Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;

JLoader::import('joomla.filesystem.folder');

/**
 * Test ingesting metadata and assets.
 *
 * @package     JHarvest.Plugin
 */
class PlgIngestTest extends JPlugin
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

                $collection = 1;

                $path = $this->buildPackage($item->id, $collection, $metadata, $assets);
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

        JFolder::create($path);

        $request = new SimpleXMLElement("<request/>");
        $request->collectionId = new SimpleXMLElement("<collectionId>".(int)$collection."</collectionId>");
        $request->metadata = new SimpleXMLElement("<metadata/>");

        $i = 0;
        foreach ($metadata as $key=>$field) {
            foreach ($field as $value) {
                $element = $request->metadata->addChild("field");
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
            fwrite(STDOUT, "Asset Name ".htmlspecialchars($asset->name)."\n");
            fwrite(STDOUT, "Asset Type ".$asset->type."\n");
            fwrite(STDOUT, "Asset Url ".$asset->url."\n");

            $bitstream = $bitstreams->addChild("bitstream");
            $bitstream->addChild("name", htmlspecialchars($asset->name));
            $bitstream->addChild("mimeType", $asset->type);

            $src = $asset->url;
            $dest = $path.'/'.$asset->name;

            fwrite(STDOUT, "Source ".$src." to ".$dest."\n");
            fwrite(STDOUT, "Destination ".$src." to ".$dest."\n");

            $this->download($src, $dest);

            $handle = fopen($dest, "r");

            $files[] = array(
                "name"=>$asset->name,
                "data"=>fread($handle, $asset->size));

            fclose($handle);
        }

        fwrite(STDOUT, "Package XML ".$request->saveXML()."\n\n\n");

        JFolder::delete($path);
    }

    /**
     * Downloads a file to a temporary location.
     *
     * @param  string   $src   The url to download from.
     * @param  string   $dest  The location to download to.
     */
    private function download($src, $dest)
    {
        if ($shandle = @fopen($src, 'r')) {
            $dhandle = fopen($dest, 'w');

            while (!feof($shandle)) {
                $chunk = fread($shandle, 1024);
                fwrite($dhandle, $chunk);
            }

            fclose($dhandle);
            fclose($shandle);
        }
    }
}
