<?php
/**
 * @copyright   Copyright (C) 2014-2017 KnowledgeArc Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */
defined('_JEXEC') or die;

use \Joomla\Utilities\ArrayHelper;
?>

<dl>
    <dt>Files:</dt>
    <?php foreach (ArrayHelper::getValue($displayData, "asset") as $asset) : ?>
    <dd>
        <h4><?php echo JHtml::_("link", $asset->link, $asset->link); ?></h4>

        <?php if ($asset->description) : ?>
        <p><?php echo $asset->description; ?></p>
        <?php endif; ?>
    </dd>
    <?php endforeach; ?>
</dl>
