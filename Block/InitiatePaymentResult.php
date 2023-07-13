<?php

declare(strict_types=1);

namespace Nofrixion\Payments\Block;

use Magento\Sales\Model\Order;
use Nofrixion\Model\Merchant\MerchantPayByBankSettings;
use Nofrixion\Payments\Helper\Data as NofrixionHelper;

class InitiatePaymentResult extends \Magento\Framework\View\Element\Template
{
	private NofrixionHelper $helper;
	public function __construct(
		\Magento\Framework\View\Element\Template\Context $context,
		NofrixionHelper $helper
	) {
		$this->helper = $helper;
		parent::__construct($context);
	}
	public function getPaymentRequest():array
	{
		return $this->getData('paymentRequest');
	}

	public function getOrder(): Order
	{
		$order = $this->getData('order');
		return $order;
	}
	public function getMessages(): array
	{
		$messages = $this->getData('messages');
		return $messages;
	}
}