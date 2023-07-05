<?php

declare(strict_types=1);

namespace Nofrixion\Payments\Block;

use Nofrixion\Model\Merchant\MerchantPayByBankSettings;
use Nofrixion\Payments\Helper\Data as NofrixionHelper;

class SubmitPayment extends \Magento\Framework\View\Element\Template
{
	private NofrixionHelper $helper;
	public function __construct(
		\Magento\Framework\View\Element\Template\Context $context,
		NofrixionHelper $helper
	) {
		$this->helper = $helper;
		parent::__construct($context);
	}

	public function getApiUrl()
	{
		// create Nofrixion\Client\PaymentRequest and use inherited getApiUrl() method.
		// $storeId = (int) $this->getData('order')->getStoreId();
		// return $this->helper->getApiUrl($storeId);
		return $this->helper->getApiBaseUrl() . '/api/v1';
	}

	public function getPaymentRequestId()
	{
		return $this->getData('paymentRequest')['id'];
	}

	public function getPaymentMethodTypes()
	{
		return $this->getData('paymentRequest')['paymentMethodTypes'];
	}
	public function getPispProviders(): array
	{
		return $this->helper->getPayByBankSettings();
	}
}