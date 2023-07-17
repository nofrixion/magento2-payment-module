<?php

declare(strict_types=1);

namespace Nofrixion\Payments\Block;

use Magento\Checkout\Model\Session;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order;
use Nofrixion\Payments\Helper\Data as NofrixionHelper;

class InitiatePaymentResult extends \Magento\Framework\View\Element\Template
{
	private NofrixionHelper $helper;
    protected OrderManagementInterface $orderManagement;
	public function __construct(
		\Magento\Framework\View\Element\Template\Context $context,
        Session $checkoutSession,
		NofrixionHelper $helper,
        OrderManagementInterface $orderManagement,
        QuoteFactory $quoteFactory
	) {
		parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
		$this->helper = $helper;
        $this->orderManagement = $orderManagement;
        $this->quoteFactory    = $quoteFactory;
	}
	public function getPaymentRequest():array
	{
		return $this->getData('paymentRequest');
	}
    public function cancelOrder($orderId)
    {
        try {
            $this->orderManagement->cancel($orderId);
            return true;
        } catch (\Exception $e) {
            // Handle the exception if required
            return false;
        }
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