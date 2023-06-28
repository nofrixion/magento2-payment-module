<?php

declare(strict_types=1);

namespace Nofrixion\Payments\Block;

use Nofrixion\Payments\Helper\Data as NoFrixionHelper;

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
		// create Nofrixion\Client\PaymentRequest and use inherited getApiUrl() method.
		// $storeId = (int) $this->getData('order')->getStoreId();
		// return $this->helper->getApiUrl($storeId);
		return $this->helper->getApiBaseUrl() . '/api/v1';
	}

	public function getPaymentRequestId()
	{
		return $this->getData('paymentRequest')['id'];
	}
}