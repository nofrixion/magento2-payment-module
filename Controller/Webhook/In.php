<?php

declare(strict_types=1);

namespace Nofrixion\Payments\Controller\Webhook;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Store\Model\StoreManagerInterface;
use Nofrixion\Payments\Helper\Data as NoFrixionHelper;
use Psr\Log\LoggerInterface;

class In implements ActionInterface
{
    private LoggerInterface $logger;
    private NoFrixionHelper $nofrixionHelper;
    private OrderSender $orderSender;
    private RequestInterface $request;
    private StoreManagerInterface $storeManager;
    private JsonFactory $resultJsonFactory;

    public function __construct(RequestInterface $request, NoFrixionHelper $helper, OrderSender $orderSender, StoreManagerInterface $storeManager, JsonFactory $resultJsonFactory, LoggerInterface $logger)
    {
        $this->request = $request;
        $this->nofrixionHelper = $helper;
        $this->orderSender = $orderSender;
        $this->storeManager = $storeManager;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->logger = $logger;
    }

    public function execute()
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        $paymentRequestId = $this->request->getParam('id');
        $resultJson = $this->resultJsonFactory->create();

        if ($paymentRequestId) {

            $this->logger->info('Nofrixion_Payments: success callback for payment request ' . $paymentRequestId);

            $paymentRequest = $this->nofrixionHelper->getPaymentRequest($paymentRequestId, $storeId);
            $order = $this->nofrixionHelper->processPayment($paymentRequest);

            // Send order confirmation email on success.
            $this->logger->info('Nofrixion_Payments: Sending order email for order #: ' . $order->getIncrementId());
            $this->orderSender->send($order, true);

            return $resultJson->setData(['order_id' => (int)$order->getId(), 'order_increment_id' => $order->getIncrementId(), 'order_state' => $order->getState(), 'order_status' => $order->getStatus()]);
        } else {
            $this->logger->error('Nofrixion_Payments: received success callback with missing payment request Id.');
            $resultJson->setStatusHeader(400);
            return $resultJson->setData(['error_msg' => 'Missing querystring parameter "id"']);
        }
    }
}
