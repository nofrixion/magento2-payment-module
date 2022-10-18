<?php
declare(strict_types=1);

namespace Nofrixion\Payments\Model\Payment;

class Nofrixion extends \Magento\Payment\Model\Method\AbstractMethod
{

    protected $_code = "nofrixion";
    protected $_isOffline = true;

    public function isAvailable(
        \Magento\Quote\Api\Data\CartInterface $quote = null
    ) {
        return parent::isAvailable($quote);
    }
}

