<?php

declare(strict_types=1);

namespace Nofrixion\Payments\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\OrderRepository;
use Magento\Store\Model\ScopeInterface;
use NoFrixion\Client\PaymentRequest;
use Nofrixion\Payments\Model\OrderStatuses;
use NoFrixion\Util\PreciseNumber;
use Psr\Log\LoggerInterface;

class Data
{
    private ScopeConfigInterface $scopeConfig;
    private UrlInterface $url;
    private LoggerInterface $logger;
    private OrderRepository $orderRepository;
    private TransactionFactory $transactionFactory;

    public const ORDER_ID_SEPARATOR = '-';


    public function __construct(
        ScopeConfigInterface $scopeConfig,
        UrlInterface $url,
        OrderRepository $orderRepository,
        TransactionFactory $transactionFactory,
        LoggerInterface $logger,
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->url = $url;
        $this->orderRepository = $orderRepository;
        $this->transactionFactory = $transactionFactory;
        $this->logger = $logger;
    }

    public function getPaymentRequestClient(?int $storeId = null): PaymentRequest
    {
        if ($this->isProductionMode($storeId)) {
            $apiToken = $this->scopeConfig->getValue('payment/nofrixion/api_token_production', ScopeInterface::SCOPE_STORE, $storeId);
        } else {
            $apiToken = $this->scopeConfig->getValue('payment/nofrixion/api_token_sandbox', ScopeInterface::SCOPE_STORE, $storeId);
        }
        $baseUrl = $this->getApiBaseUrl();
        $client = new PaymentRequest($baseUrl, $apiToken);
        return $client;
    }

    public function isProductionMode(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag('payment/nofrixion/is_production', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getApiBaseUrl(): string
    {
        if ($this->isProductionMode()) {
            return 'https://api.nofrixion.com';
        } else {
            return 'https://api-sandbox.nofrixion.com';
        }
    }


    public function createPaymentRequest(Order $order): array
    {
        $storeId = (int)$order->getStoreId();
        $amount = $order->getTotalDue();
        $customerEmail = $order->getCustomerEmail();
        $currency = $order->getOrderCurrencyCode();
        $paymentMethodTypes = array("card", "pisp");
        $originUrl = $this->url->getBaseUrl(['_store' => $storeId]);
        $nofrixionOrderId = $this->encodeOrderId($order);
        $callbackUrl = $this->url->getUrl('nofrixion/redirect/returnAfterPayment', ['_secure' => true, 'nofrixion_order_id' => $nofrixionOrderId]);
        $amount = PreciseNumber::parseString((string)$amount);
        if ($order->getCustomerId()) {
            $customerId = (string)$order->getCustomerId();
        } else {
            $customerId = null;
        }

        $createCardToken = true;
        $client = $this->getPaymentRequestClient($storeId);
        $originUrl = str_replace('http://', 'https://', $originUrl);
        $paymentRequest = $client->createPaymentRequest($originUrl, $callbackUrl, $amount, $customerEmail, $currency, "pisp", $nofrixionOrderId, $createCardToken, $customerId);

        return $paymentRequest;
    }

    public function getPaymentRequest(string $id, int $storeId): array
    {
        $client = $this->getPaymentRequestClient($storeId);
        return $client->getPaymentRequest($id);
    }

    public function processPayment(string $paymentRequestId, int $storeId): OrderInterface
    {
        $paymentRequest = $this->getPaymentRequest($paymentRequestId, $storeId);
        $nofrixionOrderId = $paymentRequest['orderID'];
        $magentoOrderId = $this->decodeOrderId($nofrixionOrderId);

        $order = $this->orderRepository->get($magentoOrderId);

        $status = $paymentRequest['status'] ?? null;

        $newStatus = null;

        switch ($status) {
            case 'OverPaid':
                $newStatus = OrderStatuses::STATUS_CODE_OVERPAID;

            case 'FullyPaid':
                if ($order->getTotalDue() > 0) {
                    if ($newStatus === null) {
                        $newStatus = OrderStatuses::STATUS_CODE_PAID_CORRECTLY;
                    }

                    $msg = 'Customer paid ' . $paymentRequest['amount'] . ' ' . $paymentRequest['currency'] . ' using the NoFrixion payment request with ID ' . $paymentRequest['id'];
                    $order->addCommentToStatusHistory($msg, $newStatus, true);

                    $invoice = $order->prepareInvoice();
                    $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);

                    // We need to set this so the "Refund" button appears when creating a credit memo for an invoice.
                    // Refunds only work when creating a credit memo for an invoice. Creating a credit memo for an order (and not a specific invoice) will not show the "Refund" button. Only the "Refund Offline" button will show.
                    $invoice->setTransactionId($paymentRequest['id']);

                    $invoice->register();

                    $saveTransaction = $this->transactionFactory->create();
                    $saveTransaction->addObject($invoice);
                    $saveTransaction->addObject($order);
                    $saveTransaction->save();
                }

                break;
            case 'PartiallyPaid':
                // TODO This is tricky because we don't know which items were paid and which items not. So how can we know what to invoice?
            case 'Voided':
                // TODO What should we do? Cancel the order?
            case 'Authorized':
                // TODO Should we do something or just wait for another status?
            default:
                $this->logger->log('Unsupported status "' . $status . '" for payment request ID ' . $paymentRequestId);
        }

        return $order;
    }

    public function getPaymentRequestByOrderId(mixed $nofrixionOrderId): array
    {
        return $this->getPaymentRequestClient()->getPaymentRequestByOrderId($nofrixionOrderId);
    }

    public function encodeOrderId(Order $order): string
    {
        $r = $order->getIncrementId() . self::ORDER_ID_SEPARATOR . time();
        return $r;
    }

    public function decodeOrderId(string $nofrixionOrderId): string
    {
        $r = explode(self::ORDER_ID_SEPARATOR, $nofrixionOrderId)[0];
        return $r;
    }

    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if ($payment instanceof Payment) {
            $creditmemo = $payment->getCreditmemo();
            $invoice = $creditmemo->getInvoice();
            $paymentRequestId = $invoice->getTransactionId();

            $order = $payment->getOrder();
            $storeId = (int)$order->getStoreId();

            $client = $this->getPaymentRequestClient($storeId);
            // Magento stores decimals with 4 places, so we do the same
            if ($amount === null || round((float)$amount, 4) === round((float)$creditmemo->getGrandTotal(), 4)) {
                $client->voidAllPaymentsForPaymentRequest($paymentRequestId);
            } else {
                throw new \RuntimeException('Cannot void the payment request with ID ' . $paymentRequestId . ' because only full refunds are supported and not partial ones.');
            }
        } else {
            throw new \InvalidArgumentException('Argument $payment should be an instance of ' . Payment::class);
        }
    }


}
