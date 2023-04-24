<?php

namespace Nofrixion\Payments\Plugin\Sales\Order\Email\Sender;

use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order;

class OrderSenderPlugin
{
    private $logger;

    public function __construct(
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    public function aroundSend(
        OrderSender $subject,
        \Closure $proceed,
        Order $order,
        $forceSyncMode = false
    ) {
        $returnValue = null;

        $orderStatus = $order->getStatus();
        $paymentMethodCode = $order->getPayment()->getMethod();

        // Proceed as normal for non-NoFrixion payment, or if payment status matches completed NoFrixion status.
        if ($paymentMethodCode != 'nofrixion' || $orderStatus === 'complete' || $orderStatus === 'nofrixion_paid_correctly') {
            $returnValue = $proceed($order, $forceSyncMode);
        }

        return $returnValue;
    }
}