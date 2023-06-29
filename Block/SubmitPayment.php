<?php

declare(strict_types=1);

namespace NoFrixion\Payments\Block;

use NoFrixion\Model\Merchant\MerchantPayByBankSettings;
use NoFrixion\Payments\Helper\Data as NoFrixionHelper;

class SubmitPayment extends \Magento\Framework\View\Element\Template
{
	private NoFrixionHelper $helper;
	public function __construct(
		\Magento\Framework\View\Element\Template\Context $context,
		NoFrixionHelper $helper
	) {
		$this->helper = $helper;
		parent::__construct($context);
	}

	public function getApiUrl()
	{
		// create NoFrixion\Client\PaymentRequest and use inherited getApiUrl() method.
		// $storeId = (int) $this->getData('order')->getStoreId();
		// return $this->helper->getApiUrl($storeId);
		return $this->helper->getApiBaseUrl() . '/api/v1';
	}

	public function getPaymentRequestId()
	{
		return $this->getData('paymentRequest')['id'];
	}

	public function getPispProviders(): MerchantPayByBankSettings
	{
		$order = $this->getData('order');
		return $this->helper->getMerchantPayByBankSettings($order);
	}
}