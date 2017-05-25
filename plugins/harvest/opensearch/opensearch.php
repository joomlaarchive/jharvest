<?php
/**
 * @copyright   Copyright (C) 2014-2017 KnowledgeArc Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die;

use Joomla\Registry\Registry;
use \JHttpFactory;
use \JArrayHelper;
use \DomDocument;
use \DOMXPath;
use \SimpleXMLElement;

/**
 * Handles importing items via an OpenSearch compliant search engine.
 *
 * @package     JHarvest.Plugin
 */
class PlgHarvestOpenSearch extends JPlugin
{
    /**
     * Attempts to discover whether the harvest configuration points to an OpenSearch-enabled url.
     *
     * @param   string     $sourceUrl  The url to discover.
     *
     * @return  JRegistry  A OpenSearch description as a JRegistry or false if no description
     * can be found.
     */
    public function onJHarvestDiscover($sourceUrl)
    {
        return $this->discover($sourceUrl);
    }

    /**
     * Recursively discovers the atom/rss OpenSearch url.
     *
     * @param   string     $sourceUrl  The url to discover.
     *
     * @return  JRegistry  A OpenSearch description as a JRegistry or false if no description
     * can be found.
     */
    private function discover($sourceUrl)
    {
        $discovered = false;

        $url = new JUri($sourceUrl);
        $http = JHttpFactory::getHttp();
        $response = $http->get($url);

        if ((int)$response->code === 200) {
            $contentType = JArrayHelper::getValue($response->headers, 'Content-Type');
            $contentType = $this->parseContentType($contentType);

            if ($contentType === 'text/html') {
                $dom = new DomDocument();
                $dom->loadHTML($response->body);
                $xpath = new DOMXPath($dom);

                $opendocument = '//html/head/link[@type="application/opensearchdescription+xml"]';
                $links = $xpath->query($opendocument);

                if (count($links)) {
                    $link = $dom->importNode($links->item(0), true);

                    $href = new JUri($link->getAttribute('href'));

                    // sometimes the opensearch url is not complete so complete it.
                    if (!$href->getScheme()) {
                        $url->setQuery(array());
                        $url->setPath($href);
                        $url->setQuery($href->getQuery());
                    }

                    $url->setVar('source', $sourceUrl);

                    $discovered = $this->discover((string)$url);
                }
            } else if ($contentType === 'application/opensearchdescription+xml') {
                $originalUrl = $url->getVar('source');

                $xml = new SimpleXMLElement($response->body);

                if (isset($xml->Url)) {
                    $urls = $xml->Url;
                    $i = 0;
                    while (($url = $urls[$i]) && !$discovered) {
                        $template = JArrayHelper::getValue($url, 'template', null, 'string');
                        $type = JArrayHelper::getValue($url, 'type', null, 'string');

                        $link = new JUri($template);
                        $queries = $link->getQuery(true);
                        //@todo a search/replace will probably suffice.
                        foreach ($queries as $keyq => &$valueq) {
                            if ($valueq === "{searchTerms}") {
                                $queries[$keyq] = JUri::getInstance($originalUrl)->getVar($keyq);
                                break;
                            }
                        }

                        $link->setQuery($queries);

                        // don't try and discover the html search.
                        if (strpos($type, 'text/html') === false) {
                            $discovered = $this->discover((string)$link);
                        }

                        $i++;
                    }
                }
            } else if (array_search($contentType, array('application/xml', 'text/xml', 'application/atom+xml', 'application/rss+xml')) !== false) {
                //@todo JUri not updating url via setVar. May need more testing.
                $discovered = new JRegistry;
                $discovered->set('discovery.type', 'opensearch');
                $discovered->set('discovery.url', (string)$sourceUrl);
                $discovered->set('discovery.plugin.type', (string)$contentType);
            }

        }

        return $discovered;
    }

    /**
     * Captures the onJHarvestRetrieve event, retrieving records via the configured
     * OpenSearch results.
     *
     * @param  JObject  $harvest  The harvest information.
     */
    public function onJHarvestRetrieve($harvest)
    {
        if ($harvest->get('params')->get('discovery.type') != 'opensearch') {
            return;
        }

        $templateUrl = $harvest->get('params')->get('discovery.url');

        $http = JHttpFactory::getHttp();
        $parameters = array(
            "{startIndex?}"=>0,
            "{startPage?}"=>0,
            "{count?}"=>100,
            "{language}"=>urlencode(JFactory::getLanguage()->getName()),
            "{inputEncoding}"=>"UTF-8",
            "{outputEncoding}"=>"UTF-8");

        $dom = new DomDocument();
        $count = 0; // the number of records to retrieve.

        do {
            $url = new JUri($templateUrl);
            $queries = $url->getQuery(true);

            foreach ($queries as $keyq=>$valueq) {
                if (array_key_exists($valueq, $parameters)) {
                    $queries[$keyq] = $parameters[$valueq];
                }
            }

            $url->setQuery($queries);

            $response = $http->get($url);

            if ((int)$response->code === 200) {
                $reader = new XMLReader;
                $reader->xml($response->body);

                while($reader->read()) {
                    if ($reader->nodeType == XmlReader::ELEMENT) {
                        if ($reader->localName == 'entry') {
                            $entry = simplexml_import_dom($dom->importNode($reader->expand(), true));
                            $this->cache($harvest, $entry);
                        }

                        if ($reader->localName == 'item') {
                            $entry = simplexml_import_dom($dom->importNode($reader->expand(), true));
                            $this->cache($harvest, $entry);
                        }

                        if ($reader->localName == 'totalResults') {
                            $totalResults = simplexml_import_dom($dom->importNode($reader->expand(), true));

                            if (!$count) {
                                $count = (int)$totalResults;
                            }
                        }
                    }
                }
            }

            $parameters['{startIndex?}']+=$parameters['{count?}'];
            $parameters['{startPage?}']+=1;
        } while($parameters['{startIndex?}'] < $count);
    }

    protected function cache($harvest, $data)
    {
        $contentType = $harvest->get('params')->get('discovery.plugin.type');

        if (isset($data->id)) {
            $identifier = (string)$data->id;
        } else {
            $identifier = (string)$data->link;
        }

        if ($identifier) {
            $metadata = array();

            // suppress duplicate attribute errors.
            libxml_use_internal_errors(true);

            $dom = new DOMDocument;
            $dom->loadHTMLFile($identifier);
            $xpath = new DOMXPath($dom);

            $tags = $xpath->query('//head/meta');

            foreach ($tags as $tag) {
                $key = JString::strtolower($tag->getAttribute('name'));
                $metadata[$key] = $tag->getAttribute('content');
            }

            $cache = array("metadata"=>$metadata);
        }
    }
}
