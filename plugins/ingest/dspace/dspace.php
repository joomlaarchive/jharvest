<?php
/**
 * @package     Ingest.DSpace.Plugin
 *
 * @copyright   Copyright (C) 2014-2017 KnowledgeArc Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;

JLoader::import('joomla.filesystem.folder');

/**
 * Ingests metadata and assets into DSpace.
 *
 * @package  Ingest.DSpace.Plugin
 */
class PlgIngestDSpace extends JPlugin
{
    protected $autoloadLanguage = true;

    /**
     * Ingest a harvested item.
     *
     * @param  stdClass   $item    The harvested item.
     * @param  JRegistry  $params  The harvester params.
     */
    public function onJHarvestItemIngest($item, $params)
    {
        $this->params->merge($params);

        $data = json_decode($item->data);

        $metadata = $data->metadata;
        $assets = $data->assets;

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
            fwrite(STDOUT, "item created: ".(string)$response->body."\n");
        } else {
            fwrite(STDOUT, print_r($response, true)."\n");
        }

        JFile::delete($path);
    }

    /**
     * Add the assets form field to the dspace form.
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

        $registry = new \Joomla\Registry\Registry;
        $registry->loadFile(__DIR__."/crosswalk.json", "JSON");

        $i = 0;
        foreach ($metadata as $key=>$field) {
            foreach ($field as $value) {
                $element = $request->metadata->addChild("field");

                $element->name = $registry->get($key);
                $element->value = $value;
                $i++;
            }
        }

        $bundle = $request->addChild("bundles")->addChild("bundle");

        $bundle->addChild("name", "ORIGINAL");
        $bitstreams = $bundle->addChild("bitstreams");

        if (!class_exists('ZipArchive')) {
            throw new Exception('Zip library not installed.');
        }

        $package = new ZipArchive;

        if ($package->open($zip, ZipArchive::CREATE) === false) {
            throw new Exception('Cannot open zip file '.$zip);
        }

        foreach ($assets as $asset) {
            $bitstream = $bitstreams->addChild("bitstream");
            $bitstream->addChild("name", htmlspecialchars($asset->name, ENT_XML1, 'UTF-8'));
            $bitstream->addChild("mimeType", $asset->type);

            $src = $asset->url;
            $dest = $path.'/'.$asset->name;

            fwrite(STDOUT, "Fetching ".$src." to ".$dest."\n");

            $this->download($src, $dest);

            $package->addFile($dest, $asset->name);
        }

        $package->addFromString('package.xml', $request->saveXML());

        $package->close();

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
        $fp = fopen($dest, 'w+');

        //@TODO preferred method when joomla curl transport can handle bool(true).
        //$http = JHttpFactory::getHttp(null, 'curl');
        //$http->setOption("transport.curl", [CURLOPT_FILE=>$fp]);
        //$http->get($src, null, 50);

        set_time_limit(0);
        $fp = fopen($dest, 'w+');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $src);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPGET, true);

        curl_exec($ch);

        curl_close($ch);
        fclose($fp);
    }
}
