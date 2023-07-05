<?php

declare(strict_types=1);

namespace Nofrixion\Payments\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\StatusFactory;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\ResourceModel\Order\Status as StatusResource;
use Magento\Sales\Model\ResourceModel\Order\StatusFactory as StatusResourceFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Nofrixion\Client\PaymentRequest;
use Nofrixion\Client\MerchantClient;
use Nofrixion\Payments\Model\OrderStatuses;
use Nofrixion\Util\PreciseNumber;
use Psr\Log\LoggerInterface;

class Data
{
    private ScopeConfigInterface $scopeConfig;
    private UrlInterface $url;
    private LoggerInterface $logger;
    private OrderRepository $orderRepository;
    private TransactionFactory $transactionFactory;
    private StatusFactory $statusFactory;
    private StatusResourceFactory $statusResourceFactory;
    private StoreManagerInterface $storeManager;

    public const ORDER_ID_SEPARATOR = '-';
    private StatusResource\CollectionFactory $statusCollectionFactory;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        UrlInterface $url,
        OrderRepository $orderRepository,
        TransactionFactory $transactionFactory,
        LoggerInterface $logger,
        StatusFactory $statusFactory,
        StatusResourceFactory $statusResourceFactory,
        \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory $statusCollectionFactory,
        StoreManagerInterface $storeManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->url = $url;
        $this->orderRepository = $orderRepository;
        $this->transactionFactory = $transactionFactory;
        $this->logger = $logger;
        $this->statusFactory = $statusFactory;
        $this->statusResourceFactory = $statusResourceFactory;
        $this->statusCollectionFactory = $statusCollectionFactory;
        $this->storeManager = $storeManager;
    }

    public function isProductionMode(?int $storeId = null): bool
    {
        $paymentMode = $this->scopeConfig->getValue('payment/nofrixion/mode', ScopeInterface::SCOPE_STORE, $storeId);
        if ($paymentMode === '1') {
            return true;
        } else {
            return false;
        }
    }

    public function getApiBaseUrl(): string
    {
        if ($this->isProductionMode()) {
            return 'https://api.nofrixion.com';
        } else {
            return 'https://api-sandbox.nofrixion.com';
        }
    }

    public function getApiToken(?int $storeId = null): string
    {
        if ($this->isProductionMode($storeId)) {
            $apiToken = $this->scopeConfig->getValue('payment/nofrixion/api_token_production', ScopeInterface::SCOPE_STORE, $storeId);
        } else {
            $apiToken = $this->scopeConfig->getValue('payment/nofrixion/api_token_sandbox', ScopeInterface::SCOPE_STORE, $storeId);
        }
        return $apiToken;
    }
    
    public function getMerchantClient(?int $storeId = null): MerchantClient
    {
        $apiToken = $this->getApiToken($storeId);
        $baseUrl = $this->getApiBaseUrl();
        $client = new MerchantClient($baseUrl, $apiToken);
        return $client;
    }
    public function getPayByBankSettings(): array
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        $client = $this->getMerchantClient($storeId);
        $merchantId = $client->whoAmIMerchant()->id;
        return $client->getMerchantPayByBankSettings($merchantId);
    }
    
    public function getPaymentRequestClient(?int $storeId = null): PaymentRequest
    {
        $apiToken = $this->getApiToken($storeId);
        $baseUrl = $this->getApiBaseUrl();
        $client = new PaymentRequest($baseUrl, $apiToken);
        return $client;
    }

    public function createPaymentRequest(Order $order): array
    {
        $storeId = (int) $order->getStoreId();
        $amount = $order->getTotalDue();
        $customerEmail = $order->getCustomerEmail();
        $currency = $order->getOrderCurrencyCode();
        $paymentMethodTypes = $this->scopeConfig->getValue('payment/nofrixion/payment_options', 'stores', $storeId);
        $paymentMethodTypes = explode(',', $paymentMethodTypes);
        $originUrl = $this->url->getBaseUrl(['_store' => $storeId]);
        $nofrixionOrderId = $this->encodeOrderId($order);
        $callbackUrl = $this->url->getUrl('nofrixion/redirect/returnAfterPayment', ['_store' => $storeId, '_secure' => true, 'nofrixion_order_id' => $nofrixionOrderId]);
        $amount = PreciseNumber::parseString((string) $amount);
        if ($order->getCustomerId()) {
            $customerId = (string) $order->getCustomerId();
        } else {
            $customerId = null;
        }

        $createCardToken = true;
        $client = $this->getPaymentRequestClient($storeId);
        $originUrl = str_replace('http://', 'https://', $originUrl);

        $webhookUrl = $this->url->getUrl('nofrixion/webhook/in', ['_secure' => true]);

        $paymentRequest = $client->createPaymentRequest($originUrl, $callbackUrl, $amount, $customerEmail, $currency, $paymentMethodTypes, $nofrixionOrderId, $createCardToken, $customerId, false, false, $webhookUrl);

        return $paymentRequest;
    }

    public function getPaymentRequest(string $id, int $storeId): array
    {
        $client = $this->getPaymentRequestClient($storeId);
        return $client->getPaymentRequest($id);
    }

    public function processPayment(array $paymentRequest): OrderInterface
    {
        $nofrixionOrderId = $paymentRequest['orderID'];
        $magentoOrderId = $this->decodeOrderId($nofrixionOrderId);

        $order = $this->orderRepository->get($magentoOrderId);

        $status = $paymentRequest['status'] ?? null;

        $createInvoice = true;
        $isPaid = false;
        $newStatus = null;

        switch ($status) {
            case 'Authorized':
                $newStatus = OrderStatuses::STATUS_CODE_AUTHORIZED_PAYMENT;
                $createInvoice = false;
                $isPaid = true;
                break;
            case 'OverPaid':
                $newStatus = OrderStatuses::STATUS_CODE_OVERPAID;
                $isPaid = true;
                break;
            case 'FullyPaid':
                $isPaid = true;
                $newStatus = OrderStatuses::STATUS_CODE_PAID_CORRECTLY;

                break;
            case 'PartiallyPaid':
                // TODO This is tricky because we don't know which items were paid and which items not. So how can we know what to invoice?
                break;
            case 'Voided':
                // TODO What should we do? Cancel the order?
                break;
            default:
                $this->logger->log('Unsupported status "' . $status . '" for payment request ID ' . $paymentRequest['id']);
        }

        if ($isPaid && $newStatus && $order->getTotalDue() > 0) {

            if ($newStatus !== $order->getStatus()) {
                if ($createInvoice) {
                    $msg = 'Customer paid ' . $paymentRequest['amount'] . ' ' . $paymentRequest['currency'] . ' using the NoFrixion payment request with ID ' . $paymentRequest['id'];
                } else {
                    $msg = 'Customer authorized ' . $paymentRequest['amount'] . ' ' . $paymentRequest['currency'] . ' using the NoFrixion payment request with ID ' . $paymentRequest['id'];
                }
                $order->addCommentToStatusHistory($msg, $newStatus, true);

                // Magento bugfix: Set the matching "state" on the order. Apparently Magento does not automatically set the "state" when the "status" changes.
                $this->fixOrderState($order);
            }

            $saveTransaction = $this->transactionFactory->create();

            if ($createInvoice) {
                $invoice = $order->prepareInvoice();
                $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);

                // We need to set this so the "Refund" button appears when creating a credit memo for an invoice.
                // Refunds only work when creating a credit memo for an invoice. Creating a credit memo for an order (and not a specific invoice) will not show the "Refund" button. Only the "Refund Offline" button will show.
                $invoice->setTransactionId($paymentRequest['id']);

                $invoice->register();
                $saveTransaction->addObject($invoice);
            }

            $saveTransaction->addObject($order);
            $saveTransaction->save();
        }
        return $order;
    }

    public function getPaymentRequestByOrderId(mixed $nofrixionOrderId): array
    {
        return $this->getPaymentRequestClient()->getPaymentRequestByOrderId($nofrixionOrderId);
    }

    public function encodeOrderId(Order $order): string
    {
        $r = $order->getId() . self::ORDER_ID_SEPARATOR . time();
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
            $storeId = (int) $order->getStoreId();

            $client = $this->getPaymentRequestClient($storeId);
            // Magento stores decimals with 4 places, so we do the same
            if ($amount === null || round((float) $amount, 4) === round((float) $creditmemo->getGrandTotal(), 4)) {
                $client->voidAllPaymentsForPaymentRequest($paymentRequestId);
            } else {
                throw new \RuntimeException('Cannot void the payment request with ID ' . $paymentRequestId . ' because only full refunds are supported and not partial ones.');
            }
        } else {
            throw new \InvalidArgumentException('Argument $payment should be an instance of ' . Payment::class);
        }
    }


    public function addNewStatusToState(string $state, array $statusData): void
    {
        /* @var StatusResource $statusResource */
        $statusResource = $this->statusResourceFactory->create();
        $status = $this->statusFactory->create();
        $status->setData($statusData);
        try {
            $statusResource->save($status);
        } catch (AlreadyExistsException $exception) {
            return;
        }
        $status->assignState($state, false, true);
    }

    private function fixOrderState(OrderInterface $order): void
    {
        $status = $order->getStatus();

        /* @var $statusCollection \Magento\Sales\Model\ResourceModel\Order\Status\Collection\Interceptor */
        $statusCollection = $this->statusCollectionFactory->create();
        $statusCollection->joinStates();
        $statusCollection->addFieldToFilter('main_table.status', $status);

        if ($statusCollection->count() === 1) {
            $newState = $statusCollection->getFirstItem()->getState();
            $order->setState($newState);
        }
    }


}