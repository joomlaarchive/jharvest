<?php
defined('_JEXEC') or die;

class JHarvestControllerHarvests extends JControllerAdmin
{
    public function __construct($config = array())
    {
        parent::__construct($config);

        $this->set('model_prefix', 'JHarvestModel');
        $this->set('name', 'Harvest');
    }
}
