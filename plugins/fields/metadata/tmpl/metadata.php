<?php
/**
 * @package     JHarvest.Plugin
 * @subpackage  Fields.Metadata
 *
 * @copyright   Copyright (C) 2017 KnowledgeArc Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

$value = $field->value;

if ($value == '') {
    return;
}

if (is_array($value)) {
    $value = implode(', ', $value);
}

echo htmlentities($value);
