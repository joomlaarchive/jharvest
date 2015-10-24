<?php
defined('_JEXEC') or die;

class JHarvestControllerHarvest extends JControllerForm
{
    protected function allowDiscover($data = array(), $key = 'id')
    {
        return $this->allowSave($data, $key);
    }

    public function discover()
    {
        // Check for request forgeries.
        JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));

        $app   = JFactory::getApplication();
        $lang  = JFactory::getLanguage();
        $model = $this->getModel();
        $table = $model->getTable();
        $data  = $this->input->post->get('jform', array(), 'array');
        $checkin = property_exists($table, 'checked_out');
        $context = "$this->option.edit.$this->context";
        $task = $this->getTask();

        $return = true;

        // Determine the name of the primary key for the data.
        if (empty($key)) {
            $key = $table->getKeyName();
        }

        // To avoid data collisions the urlVar may be different from the primary key.
        if (empty($urlVar)) {
            $urlVar = $key;
        }

        $recordId = $this->input->getInt($urlVar);

        // Populate the row id from the session.
        $data[$key] = $recordId;

        // Access check.
        if (!$this->allowDiscover($data, $key)) {
            $this->setMessage(JText::_('COM_JHARVEST_HARVEST_DISCOVER_NOT_PERMITTED'), 'error');

            $return = false;
        }

        // Validate the posted data.
        // Sometimes the form needs some posted data, such as for plugins and modules.
        $form = $model->getForm($data);

        if (!$form) {
            $app->enqueueMessage($model->getError(), 'error');

            $return = false;
        }

        // Test whether the data is valid.
        $validData = $model->validate($form, $data);

        // Check for validation errors.
        if ($validData === false) {
            // Get the validation messages.
            $errors = $model->getErrors();

            // Push up to three validation messages out to the user.
            for ($i = 0, $n = count($errors); $i < $n && $i < 3; $i++) {
                if ($errors[$i] instanceof Exception) {
                    $app->enqueueMessage($errors[$i]->getMessage(), 'warning');
                } else {
                    $app->enqueueMessage($errors[$i], 'warning');
                }
            }

            // Save the data in the session.
            $app->setUserState($context . '.data', $data);

            $return = false;
        }

        // Attempt to discover the data.
        if ($model->discover($validData)) {
            $this->setMessage(JText::_('COM_JHARVEST_HARVEST_DISCOVER_SUCCESS'));
        } else {
            // Discover the data in the session.
            $app->setUserState($context . '.data', $validData);

            // Redirect back to the edit screen.
            $this->setMessage(JText::_('COM_JHARVEST_HARVEST_DISCOVER_FAILED'), 'error');

            $return = false;
        }

        // Redirect back to the edit screen.
        $this->setRedirect(
            JRoute::_(
                'index.php?option=' . $this->option . '&view=' . $this->view_item
                . $this->getRedirectToItemAppend($recordId, $urlVar), false
            )
        );

        return $return;
    }
}
