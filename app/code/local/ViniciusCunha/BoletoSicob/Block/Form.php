<?php

class ViniciusCunha_BoletoSicob_Block_Form extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('ViniciusCunha/boletosicob/form.phtml');
    }
    
}