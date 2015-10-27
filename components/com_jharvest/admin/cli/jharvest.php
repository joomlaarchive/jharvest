#!/usr/bin/php
<?php
/**
 * @package JSpace
 * @subpackage CLI
 * @copyright Copyright (C) 2014 KnowledgeARC Ltd. All rights reserved.
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
use \JPlugin;
use \JLog;
use \JFactory;
use \JDate;

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

        // fool the system into thinking we are running as JSite with JSolr as the active component
        $_SERVER['HTTP_HOST'] = 'domain.com';
        JFactory::getApplication('site');

        // Disable caching.
        $config = JFactory::getConfig();
        $config->set('caching', 0);
        $config->set('cache_handler', 'file');

        try {
            if ($this->input->get('list')) {
                $this->listHarvests();
            } else if ($this->input->get('harvest')) {
                $this->harvest();
            } else {
                $this->help();
            }
        } catch (Exception $e) {
            $this->out($e->getMessage());
        }
    }

    private function listHarvests()
    {
        JModelLegacy::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR . '/models', 'JHarvestModel');
        $harvests = JModelLegacy::getInstance('Harvests', 'JHarvestModel');

        foreach ($harvests->getItems() as $harvest) {
            $params = new Registry;
            $params->loadString($harvest->params);

            $this->out($harvest->id."\t".$params->get('discovery.url'));
        }
    }

    private function harvest()
    {
        JTable::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR . '/tables');
        JModelLegacy::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR . '/models', 'JHarvestModel');

        $harvests = JModelLegacy::getInstance('Harvests', 'JHarvestModel');

        $start = new JDate('now');
        $this->out('started '.(string)$start);

        $dispatcher = JEventDispatcher::getInstance();

        JPluginHelper::importPlugin('harvest');
        JPluginHelper::importPlugin('ingest');

        foreach ($harvests->getItems() as $harvest) {
            try {
                $now = new JDate('now');

                $table = JTable::getInstance('Harvest', 'JHarvestTable');
                $table->load($harvest->id);

                $dispatcher->trigger('onJHarvestRetrieve', array($table));
                $dispatcher->trigger('onJHarvestIngest', array($table));

                $table->harvested = $now->toSql();
                $table->runs++;

                if ((bool)$table->run_once === true) {
                    $table->state = 2;
                }

                $table->store();
            } catch (Exception $e) {
                echo $e->getMessage();
                echo $e->getTraceAsString();
                $this->out($e->getMessage());
            }
        }

        $end = new JDate('now');

        $this->out('ended '.(string)$end);
        $this->out($start->diff($end)->format("%H:%I:%S"));
    }

    /**
     * Prints out the plugin's help and usage information.
     *
     */
    private function help()
    {
        $out = <<<EOT
Usage: jspace harvest [task]

Provides harvesting functions from the command line.

[task]
  --list                     Lists all available harvests.
  --harvest                  Runs each harvest, saving the ingested data to a cache table.
  -h, --help                 Prints this help.

EOT;

        $this->out($out);
    }
}

JApplicationCli::getInstance('JHarvestCli')->execute();
