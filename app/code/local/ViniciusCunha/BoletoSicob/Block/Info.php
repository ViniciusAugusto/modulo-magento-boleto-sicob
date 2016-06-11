<?php

class ViniciusCunha_BoletoSicob_Block_Info extends Mage_Payment_Block_Info
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('ViniciusCunha/boletosicob/info.phtml');
    }

}