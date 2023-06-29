<?php

declare(strict_types=1);

namespace NoFrixion\Payments\Model\Payment;

use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Payment\Model\InfoInterface;
use NoFrixion\Payments\Helper\Data as NoFrixionHelper;
use \Magento\Payment\Model\Method\Logger;

class Nofrixion extends \Magento\Payment\Model\Method\AbstractMethod
{

    public const CODE = 'nofrixion';
    protected $_code = self::CODE;
    protected $_canRefund = true;

    private NofrixionHelper $nofrixionHelper;

    public function __construct(NofrixionHelper $nofrixionHelper, \Magento\Framework\Model\Context $context, \Magento\Framework\Registry $registry, \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory, \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory, \Magento\Payment\Helper\Data $paymentData, \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig, Logger $logger, \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null, \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null, array $data = [], DirectoryHelper $directory = null)
    {
        parent::__construct($context, $registry, $extensionFactory, $customAttributeFactory, $paymentData, $scopeConfig, $logger, $resource, $resourceCollection, $data, $directory);
        $this->nofrixionHelper = $nofrixionHelper;
    }

    public function refund(InfoInterface $payment, $amount)
    {
        $this->cancel($payment, $amount);
        return $this;
    }

    public function void(InfoInterface $payment)
    {
        $this->cancel($payment);
        return $this;
    }

    public function cancel(\Magento\Payment\Model\InfoInterface $payment, $amount = null)
    {
        $this->nofrixionHelper->refund($payment, $amount);
        return $this;
    }

}

