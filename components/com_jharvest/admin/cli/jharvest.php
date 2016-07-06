<?php
/**
 * @package     JSpace
 * @subpackage  Cli
 * @copyright   Copyright (C) 2014-2016 KnowledgeArc Ltd. All rights reserved.
 * @license     This file is part of the JHarvest component for Joomla!.
 */

// Make sure we're being called from the command line, not a web interface
if (array_key_exists('REQUEST_METHOD', $_SERVER)) die();

/**
 * This is a CRON script which should be called from the command-line, not the
 * web. For example something like:
 * /usr/bin/php /path/to/site/cli/jspace.php
 */

// Set flag that this is a parent file.
define('_JEXEC', 1);

if (PHP_SAPI !== 'cli') {
    die('This is a command line only application.');
}

// Load system defines
if (file_exists(dirname(dirname(__FILE__)) . '/defines.php')) {
        require_once dirname(dirname(__FILE__)) . '/defines.php';
}

if (!defined('_JDEFINES')) {
    define('JPATH_BASE', dirname(dirname(__FILE__)));
    require_once JPATH_BASE . '/includes/defines.php';
}

define('JPATH_COMPONENT_ADMINISTRATOR', JPATH_ADMINISTRATOR . '/components/com_jharvest');

// Get the framework.
if (file_exists(JPATH_LIBRARIES . '/import.legacy.php'))
    require_once JPATH_LIBRARIES . '/import.legacy.php';
else
    require_once JPATH_LIBRARIES . '/import.php';

// Bootstrap the CMS libraries.
require_once JPATH_LIBRARIES . '/cms.php';

// Load the configuration
require_once JPATH_CONFIGURATION . '/configuration.php';

// include relevant tables.
JTable::addIncludePath(JPATH_ROOT.'/administrator/components/com_jharvest/tables');

// System configuration.
$config = new JConfig;

// Configure error reporting to maximum for CLI output.
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load Library language
$lang = JFactory::getLanguage();

// Try the finder_cli file in the current language (without allowing the loading of the file in the default language)
$lang->load('jharvest_cli', JPATH_SITE, null, false, false)
// Fallback to the finder_cli file in the default language
|| $lang->load('jharvest_cli', JPATH_SITE, null, true);

use Joomla\Registry\Registry;

JLoader::register('JHarvestHelper', __DIR__.'/../administrator/components/com_jharvest/helpers/jharvest.php');

/**
 * Simple command line interface application class.
 *
 * @package JHarvest.CLI
 */
class JHarvestCli extends JApplicationCli
{
    public function doExecute()
    {
        if ($this->input->get('h') || $this->input->get('help')) {
            $this->help();
            return;
        }

        // fool the system into thinking we are running as JSite with JHarvest as the active component
        $_SERVER['HTTP_HOST'] = 'domain.com';
        JFactory::getApplication('site');

        // Disable caching.
        $config = JFactory::getConfig();
        $config->set('caching', 0);
        $config->set('cache_handler', 'file');

        if ($this->input->get('list')) {
            $this->listHarvests();
        } else if ($this->input->get('harvest')) {
            $this->harvest();
        } else {
            $this->help();
        }
    }

    private function listHarvests()
    {
        JModelLegacy::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR . '/models', 'JHarvestModel');
        $harvests = JModelLegacy::getInstance('Harvests', 'JHarvestModel');

        foreach ($harvests->getItems() as $harvest) {
            $params = new Registry;
            $params->loadString($harvest->params);

            fwrite(STDOUT, $harvest->id."\t".$params->get('discovery.url'));
        }
    }

    private function harvest()
    {
        $GLOBALS['application'] = $this;

        JTable::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR . '/tables');
        JModelLegacy::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR . '/models', 'JHarvestModel');

        $harvests = JModelLegacy::getInstance('Harvests', 'JHarvestModel');

        $start = new JDate('now');

        JHarvestHelper::log("started ".(string)$start);

        $dispatcher = JEventDispatcher::getInstance();

        JPluginHelper::importPlugin('harvest');
        JPluginHelper::importPlugin('ingest');

        foreach ($harvests->getItems() as $harvest) {
            try {
                $now = new JDate('now');

                $table = JTable::getInstance('Harvest', 'JHarvestTable');
                $table->load($harvest->id);
                $table->now = $now;

                $dispatcher->trigger('onJHarvestRetrieve', array($table));
                $dispatcher->trigger('onJHarvestIngest', array($table));

                $query = JFactory::getDbo()->getQuery(true);
                $query->select('count(id)')->from('#__jharvest_cache');
                $total = (int)JFactory::getDbo()->setQuery($query)->loadResult();

                // only record last successful harvest which had records.
                if ($total > 0) {
                    $table->harvested = $now->toSql();
                }

                $table->runs++;

                if ((bool)$table->run_once === true) {
                    $table->state = 2;
                }

                $table->store();
            } catch (Exception $e) {
                JHarvestHelper::log($e->getMessage()."\n");
                JHarvestHelper::log($e->getTraceAsString()."\n");
            }

            // clear the cache, even if there is an error.
            JHarvestHelper::clearCache();
        }

        $end = new JDate('now');

        JHarvestHelper::log('ended '.(string)$end);
        JHarvestHelper::log($start->diff($end)->format("%H:%I:%S"));
    }

    /**
     * Prints out the plugin's help and usage information.
     *
     */
    private function help()
    {
        $version = "1.0";

        $out = <<<EOT
JHarvest CLI $version
Copyright (C) 2014-2016 KnowledgeArc Ltd
-------------------------------------------------------------------------------
JHarvest is Free Software, distributed under the terms of the GNU General
Public License version 3 or, at your option, any later version.
This program comes with ABSOLUTELY NO WARRANTY as per sections 15 & 16 of the
license. See http://www.gnu.org/licenses/gpl-3.0.html for details.
-------------------------------------------------------------------------------

Usage: jspace harvest [OPTIONS] [task]

Provides harvesting functions from the command line.

[OPTIONS]
  -v, --verbose              Writes more information.
  -q, --quiet                Suppresses all output including errors.

[task]
  --list                     Lists all available harvests.
  --harvest                  Runs each harvest, saving the ingested data to a cache table.
  -h, --help                 Prints this help.

EOT;

        $this->out($out);
    }
}

JApplicationCli::getInstance('JHarvestCli')->execute();
