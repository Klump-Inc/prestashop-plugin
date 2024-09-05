<?php
class BnplpaymentValidationModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();
        
        $this->setTemplate('module:bnplpayment/views/templates/front/validation.tpl');
    }
}
