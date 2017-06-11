<?php
/**
 * @package     JHarvest.Plugin
 * @subpackage  Fields.Metadata
 *
 * @copyright   Copyright (C) 2017 KnowledgeArc Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

JLoader::import('components.com_fields.libraries.fieldsplugin', JPATH_ADMINISTRATOR);

/**
 * Fields Metadata Plugin
 */
class PlgFieldsMetadata extends FieldsPlugin
{
    public function onCustomFieldsPrepareDom($field, DOMElement $parent, JForm $form)
    {
        JFormHelper::addFieldPath(__DIR__.'/fields');

        $fieldNode = parent::onCustomFieldsPrepareDom($field, $parent, $form);

        if (!$fieldNode) {
            return $fieldNode;
        }

        $fieldNode->setAttribute('validate', '');
        $fieldNode->setAttribute('type', 'metadata');
        $fieldNode->setAttribute("formsource", "plugins/fields/metadata/forms/text.xml");
        $fieldNode->setAttribute("buttons", "add,remove");
        $fieldNode->setAttribute('multiple', true);

        return $fieldNode;
    }
}
