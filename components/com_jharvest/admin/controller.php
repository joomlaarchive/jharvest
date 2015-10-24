<?php
defined('_JEXEC') or die;

class JHarvestController extends JControllerLegacy
{
    protected $default_view = 'harvests';

    public function display($cachable = false, $urlparams = false)
    {
        $view = $this->input->get('view', $this->default_view);
        $layout = $this->input->get('layout', $this->default_view);
        $id = $this->input->getInt('id');

        // Check for edit form.
        if ($view == 'harvest' &&
            $layout == 'edit' &&
            !$this->checkEditId('com_jharvest.edit.harvest', $id)) {
            // Somehow the person just went to the form - we don't allow that.
            $this->setError(JText::sprintf('JLIB_APPLICATION_ERROR_UNHELD_ID', $id));
            $this->setMessage($this->getError(), 'error');
            $this->setRedirect(JRoute::_('index.php?option=com_jharvest&view='.$this->default_view, false));

            return false;
        }

        parent::display();

        return $this;
    }
}
