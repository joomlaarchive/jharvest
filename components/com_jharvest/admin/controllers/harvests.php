<?php
defined('_JEXEC') or die;

class JHarvestControllerHarvests extends JControllerAdmin
{
    public function __construct($config = array())
    {
        parent::__construct($config);

        $this->set('model_prefix', 'JHarvestModel');
        $this->set('name', 'Harvests');
    }

    public function reset()
    {
        // Check for request forgeries.
        JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));

        $ids = JFactory::getApplication()->input->post->get('cid', array(), 'array');

        $model = $this->getModel();
        $return = $model->reset($ids);

        if ($return === false) {
            $message = JText::sprintf('JLIB_APPLICATION_ERROR_RESET_FAILED', $model->getError());
            $this->setRedirect(JRoute::_('index.php?option=' . $this->option . '&view=' . $this->view_list, false), $message, 'error');

            return false;
        } else {
            $message = JText::plural($this->text_prefix . '_N_ITEMS_RESET', count($ids));
            $this->setRedirect(JRoute::_('index.php?option=' . $this->option . '&view=' . $this->view_list, false), $message);

            return true;
        }
    }
}
