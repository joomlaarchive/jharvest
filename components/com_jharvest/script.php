<?php
/**
 * Installation scripts.
 *
 * @package     JHarvest
 * @copyright   Copyright (C) 2014-2017 KnowledgeArc Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die('Restricted access');

jimport('joomla.installer.helper');
jimport('joomla.filesystem.folder');

class Com_JHarvestInstallerScript
{
    public function install($parent)
    {

    }

    public function update(JAdapterInstance $adapter)
    {

    }

    public function uninstall($parent)
    {
        $src = JPATH_ROOT."/cli/jsolrcrawler.php";

        if (JFile::exists($src)) {
            if (JFile::delete($src)) {
                echo "<p>Crawler uninstalled from ".$src." successfully.</p>";
            } else {
                echo "<p>Could not uninstall crawler from ".$src.". You will need to manually remove it.</p>";
            }
        }
    }

    public function postflight($type, $parent)
    {
        $crawler = $this->installCrawler($parent);
        ?>

        <table class="adminlist table table-striped" style="width: 100%;">
            <thead>
                <tr>
                    <th class="title">Extension</th>
                    <th width="30%">Status</th>
                </tr>
            </thead>

            <tbody>
                <tr>
                    <td>JHarvest</td>
                    <td>
                        <?php if ($crawler) : ?>
                        <strong style="color: green">Installed</strong>
                        <?php else : ?>
                        <strong style="color: red">Not Installed</strong>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    private function installCrawler($parent)
    {
        $success = false;

        $src = $parent->getParent()->getPath('extension_administrator').
            '/cli/jharvest.php';

        $cli = JPATH_ROOT.'/cli/jharvest.php';

        if (JFile::exists($src)) {
            if ($success = JFile::move($src, $cli)) {
                JFolder::delete($parent->getParent()->getPath('extension_administrator').'/cli');
            }
        }

        return $success;
    }
}
