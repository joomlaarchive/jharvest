<?php
/**
 * @package     Joomla.Platform
 * @subpackage  Form
 *
 * @copyright   Copyright (C) 2017 KnowledgeArc Ltd. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('JPATH_PLATFORM') or die;

jimport('joomla.filesystem.path');

JFormHelper::loadFieldClass('subform');

/**
 * The Field to load the form inside current form.
 *
 * @Example with all attributes:
 *  <field name="field-name" type="metadata"
 *      formsource="path/to/form.xml" min="1" max="3" multiple="true" buttons="add,remove,move"
 *      groupByFieldset="false" component="com_example" client="site"
 *      label="Field Label" description="Field Description" />
 *
 * Provdes a capability layer between Subforms and how Com_Fields saves data.
 *
 * @since  3.6
 */
class JFormFieldMetadata extends JFormFieldSubform
{
    /**
     * The form field type.
     * @var    string
     */
    protected $type = 'Metadata';

    /**
     * Layout to render the form
     * @var  string
     */
    protected $layout = 'joomla.form.field.metadata.default';

    /**
     * Method to set certain otherwise inaccessible properties of the form field object.
     *
     * @param   string  $name   The property name for which to the the value.
     * @param   mixed   $value  The value of the property.
     *
     * @return  void
     *
     * @since   3.6
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'layout':
                if ($value) {
                    $this->layout = (string)$value;
                }

                break;

            default:
                parent::__set($name, $value);
        }
    }

    /**
     * Method to attach a JForm object to the field.
     *
     * @param   SimpleXMLElement  $element  The SimpleXMLElement object representing the <field /> tag for the form field object.
     * @param   mixed             $value    The form field value to validate.
     * @param   string            $group    The field name group control value.
     *
     * @return  boolean  True on success.
     *
     * @since   3.6
     */
    public function setup(SimpleXMLElement $element, $value, $group = null)
    {
        // If only one value, set as array (subform tries to decode a json string).
        if ($value && is_string($value)) {
            $value = [$value];
        }

        if (!parent::setup($element, $value, $group)) {
            return false;
        }

        return true;
    }

    /**
     * Method to get the field input markup.
     *
     * @return  string  The field input markup.
     *
     * @since   3.6
     */
    protected function getInput()
    {
        $value = $this->value ? $this->value : array();

        // Prepare data for renderer
        $data    = parent::getLayoutData();
        $tmpl    = null;
        $forms   = array();
        $control = $this->name;

        try {
            // Prepare the form template
            // We have to flatten somewhat the multi-dimensional array so that com_fields correctly
            // saves our data. So instead of [com_fields][metadata][metadata1][field] we need
            // [com_fields][metadata][metdata1].
            $formname = 'metadata' . ($this->group ? $this->group . '.' : '.').$this->fieldname;
            $tmplcontrol = $control;
            $tmpl = JForm::getInstance($formname, $this->formsource, ['control'=>$tmplcontrol]);

            $fieldsets = array_merge([""], $tmpl->getFieldsets());

            foreach ($fieldsets as $fieldset) {
                foreach ($tmpl->getFieldset($fieldset) as $field) {
                    $fieldXml = $tmpl->getFieldXml("metadata");
                    $fieldXml["name"] = $this->fieldname."X";
                    $tmpl->setField($fieldXml);
                }
            }

            $value = array_values($value);
            $c = max($this->min, min(count($value), $this->max));

            for ($i = 0; $i < $c; $i++) {
                $itemcontrol = $control;
                $itemform = JForm::getInstance($formname . $i, $this->formsource, ['control'=>$itemcontrol]);

                foreach ($fieldsets as $fieldset) {
                    foreach ($itemform->getFieldset($fieldset) as $field) {
                        $fieldXml = $itemform->getFieldXml("metadata");
                        $fieldXml["name"] = $this->fieldname.$i;
                        $itemform->setField($fieldXml);

                        if (!empty($value[$i])) {
                            $itemform->bind([$this->fieldname.$i=>$value[$i]]);
                        }
                    }
                }

                $forms[] = $itemform;
            }
        } catch (Exception $e) {
            return $e->getMessage();
        }

        $data['tmpl']      = $tmpl;
        $data['forms']     = $forms;
        $data['min']       = $this->min;
        $data['max']       = $this->max;
        $data['control']   = $control;
        $data['buttons']   = $this->buttons;
        $data['fieldname'] = $this->fieldname;
        $data['groupByFieldset'] = $this->groupByFieldset;

        $renderer = new JLayoutFile($this->layout, __DIR__."/../layouts");

        // Allow to define some JLayout options as attribute of the element
        if ($this->element['component'])
        {
            $renderer->setComponent((string) $this->element['component']);
        }

        if ($this->element['client'])
        {
            $renderer->setClient((string) $this->element['client']);
        }

        // Render
        $html = $renderer->render($data);

        // Add hidden input on front of the metadata inputs, in multiple mode
        // for allow to submit an empty value
        if ($this->multiple)
        {
            $html = '<input name="' . $this->name . '" type="hidden" value="" />' . $html;
        }

        return $html;
    }

    /**
     * Method to get the name used for the field input tag.
     *
     * @param   string  $fieldName  The field element name.
     *
     * @return  string  The name to be used for the field input tag.
     *
     * @since   3.6
     */
    protected function getName($fieldName)
    {
        $name = '';

        // If there is a form control set for the attached form add it first.
        if ($this->formControl)
        {
            $name .= $this->formControl;
        }

        // If the field is in a group add the group control to the field name.
        if ($this->group)
        {
            // If we already have a name segment add the group control as another level.
            $groups = explode('.', $this->group);

            if ($name)
            {
                foreach ($groups as $group)
                {
                    $name .= '[' . $group . ']';
                }
            }
            else
            {
                $name .= array_shift($groups);

                foreach ($groups as $group)
                {
                    $name .= '[' . $group . ']';
                }
            }
        }

        // If we already have a name segment add the field name as another level.
        if ($name)
        {
            $name .= '[' . $fieldName . ']';
        }
        else
        {
            $name .= $fieldName;
        }

        return $name;
    }
}
