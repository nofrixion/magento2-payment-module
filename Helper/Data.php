<?php

declare(strict_types=1);

namespace Nofrixion\Payments\Helper;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\StatusFactory;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\ResourceModel\Order\Status as StatusResource;
use Magento\Sales\Model\ResourceModel\Order\StatusFactory as StatusResourceFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Nofrixion\Client\PaymentRequestClient;
use Nofrixion\Client\MerchantClient;
use Nofrixion\Model\PaymentRequests\PaymentInitiationResponse;
use Nofrixion\Payments\Model\OrderStatuses;
use Nofrixion\Util\PreciseNumber;
use Psr\Log\LoggerInterface;

/**
 * Data Helper for managing NoFrixion MoneyMoov API calls from magento via the moneymoov-php library
 * 
 * @todo create Models for payment request in moneymoov-php and implement here.
 */
class Data
{
    private Session $checkoutSession;
    /**
     * scopeConfig - allows access to plugin configuration settings.
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;
    /**
     * Summary of url
     * @var UrlInterface
     */
    private UrlInterface $url;
    /**
     * Set uup logging
     * @var LoggerInterface
     */
    private LoggerInterface $logger;
    private OrderManagementInterface $orderManager;
    /**
     * Summary of orderRepository
     * @var OrderRepository
     */
    private OrderRepository $orderRepository;
    private QuoteFactory $quoteFactory;
    /**
     * Summary of transactionFactory
     * @var TransactionFactory
     */
    private TransactionFactory $transactionFactory;
    /**
     * Summary of statusFactory
     * @var Order\StatusFactory
     */
    private StatusFactory $statusFactory;
    /**
     * Summary of statusResourceFactory
     * @var StatusResourceFactory
     */
    private StatusResourceFactory $statusResourceFactory;
    private StatusResource\CollectionFactory $statusCollectionFactory;
    /**
     * Summary of storeManager
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    public const ORDER_ID_SEPARATOR = '-';
    /**
     * Summary of statusCollectionFactory
     * @var StatusResource\CollectionFactory
     */

    protected ?int $storeId;


    /**
     * Set up dependency injection from core magento framework
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\UrlInterface $url
     * @param \Magento\Sales\Model\OrderRepository $orderRepository
     * @param \Magento\Framework\DB\TransactionFactory $transactionFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Sales\Model\Order\StatusFactory $statusFactory
     * @param \Magento\Sales\Model\ResourceModel\Order\StatusFactory $statusResourceFactory
     * @param \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory $statusCollectionFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        Session $checkoutSession,
        ScopeConfigInterface $scopeConfig,
        UrlInterface $url,
        OrderRepository $orderRepository,
        TransactionFactory $transactionFactory,
        LoggerInterface $logger,
        OrderManagementInterface $orderManager,
        QuoteFactory $quoteFactory,
        StatusFactory $statusFactory,
        StatusResourceFactory $statusResourceFactory,
        \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory $statusCollectionFactory,
        StoreManagerInterface $storeManager
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->scopeConfig = $scopeConfig;
        $this->url = $url;
        $this->orderRepository = $orderRepository;
        $this->transactionFactory = $transactionFactory;
        $this->logger = $logger;
        $this->orderManager = $orderManager;
        $this->quoteFactory = $quoteFactory;
        $this->statusFactory = $statusFactory;
        $this->statusResourceFactory = $statusResourceFactory;
        $this->statusCollectionFactory = $statusCollectionFactory;
        $this->storeManager = $storeManager;

        $this->storeId = (int) $this->storeManager->getStore()->getId();
    }

    /**
     * determine if store is using sandbox or production merchant token.
     * @param mixed $storeId
     * @return bool
     */
    public function isProductionMode(?int $storeId = null): bool
    {
        $paymentMode = $this->scopeConfig->getValue('payment/nofrixion/mode', ScopeInterface::SCOPE_STORE, $storeId);
        if ($paymentMode === '1') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns sandbox or production API url as appropriate to plugin mode.
     * @return string
     */
    public function getApiBaseUrl(): string
    {
        if ($this->isProductionMode()) {
            return 'https://api.nofrixion.com';
        } else {
            return 'https://api-sandbox.nofrixion.com';
        }
    }

    /**
     * Returns sandbox or production merchant token as appropriate to plugin mode.
     * @param mixed $storeId
     * @return string
     */
    public function getApiToken(?int $storeId = null): string
    {
        if ($this->isProductionMode($storeId)) {
            $apiToken = $this->scopeConfig->getValue('payment/nofrixion/api_token_production', ScopeInterface::SCOPE_STORE, $storeId);
        } else {
            $apiToken = $this->scopeConfig->getValue('payment/nofrixion/api_token_sandbox', ScopeInterface::SCOPE_STORE, $storeId);
        }
        return $apiToken;
    }

    /**
     * Summary of getMerchantClient
     * @param mixed $storeId
     * @return \Nofrixion\Client\MerchantClient
     */
    public function getMerchantClient(?int $storeId = null): MerchantClient
    {
        $apiToken = $this->getApiToken($storeId);
        $baseUrl = $this->getApiBaseUrl();
        $client = new MerchantClient($baseUrl, $apiToken);
        return $client;
    }
    /**
     * Summary of getPayByBankSettings
     * @return array
     */
    public function getPayByBankSettings(): array
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        $client = $this->getMerchantClient($storeId);
        $merchantId = $client->whoAmIMerchant()->id;

        $settings = $client->getMerchantPayByBankSettings($merchantId);
        // quick filter base on currency, may not be needed after API update
        $currency = $this->scopeConfig->getValue('payment/nofrixion/pisp_currency', ScopeInterface::SCOPE_STORE, $storeId);

        $settings = array_values(array_filter($settings, function ($bank) use ($currency) {
            return $bank->currency === $currency;
        }));
        return $settings;
    }

    public function initiatePayByBank(
        string $paymentRequestId,
        string $bankId,
        ?PreciseNumber $amount
    ): PaymentInitiationResponse {
        $storeId = (int) $this->storeManager->getStore()->getId();
        $client = $this->getPaymentRequestClient($storeId);
        return $client->initiatePayByBank($paymentRequestId, $bankId, $amount);
    }

    /**
     * Summary of getPaymentRequestClient
     * @param mixed $storeId
     * @return \Nofrixion\Client\PaymentRequestClient
     */
    public function getPaymentRequestClient(?int $storeId = null): PaymentRequestClient
    {
        $apiToken = $this->getApiToken($storeId);
        $baseUrl = $this->getApiBaseUrl();
        $client = new PaymentRequestClient($baseUrl, $apiToken);
        return $client;
    }

    /**
     * Summary of createPaymentRequest
     * @param \Magento\Sales\Model\Order $order
     * @return array
     * @todo paymentMethodTypes should reflect method used at checkout.
     */
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

    /**
     * Gets PaymentRequest by Payment Request Id
     * @param string $id
     * @param int $storeId
     * @return array
     */
    public function getPaymentRequest(string $id, int $storeId): array
    {
        $client = $this->getPaymentRequestClient($storeId);
        return $client->getPaymentRequest($id);
    }

    /**
     * deletePaymentRequest - deletes a payment request
     * @param string $id The payment request Id
     */
    public function deletePaymentRequest(string $id)
    {
        $client = $this->getPaymentRequestClient($this->storeId);
        return $client->deletePaymentRequest($id);
    }

    /**
     * Summary of processPayment
     * @param array $paymentRequest
     * @return \Magento\Sales\Api\Data\OrderInterface
     */
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

    /**
     * Summary of getPaymentRequestByOrderId
     * @param mixed $nofrixionOrderId
     * @return array
     */
    public function getPaymentRequestByOrderId(mixed $nofrixionOrderId): array
    {
        return $this->getPaymentRequestClient()->getPaymentRequestByOrderId($nofrixionOrderId);
    }

    /**
     * Summary of encodeOrderId
     * @param \Magento\Sales\Model\Order $order
     * @return string
     */
    public function encodeOrderId(Order $order): string
    {
        $r = $order->getId() . self::ORDER_ID_SEPARATOR . time();
        return $r;
    }

    /**
     * Summary of decodeOrderId
     * @param string $nofrixionOrderId
     * @return string
     */
    public function decodeOrderId(string $nofrixionOrderId): string
    {
        $r = explode(self::ORDER_ID_SEPARATOR, $nofrixionOrderId)[0];
        return $r;
    }

    /**
     * Summary of refund
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param mixed $amount
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @return void
     */
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

    /**
     * restoreCart - loads an order back into the magento cart (quote)
     * @param Magento\Sales\Model\Order $order
     * @return void
     */
    public function restoreCart($order)
    {
        $quote = $this->quoteFactory->create()->loadByIdWithoutStore($order->getQuoteId());
        if ($quote->getId()) {
            $quote->setIsActive(1)->setReservedOrderId(null)->save();
            $this->checkoutSession->replaceQuote($quote);

            // if we restore order to cart, also cancel order
            $this->orderManager->cancel($order->getId());
            $order->setStatus(Order::STATE_CANCELED);
            $order->addStatusToHistory(Order::STATE_CANCELED, '', false);
            $order->save();
        }
    }

    /**
     * Summary of addNewStatusToState
     * @param string $state
     * @param array $statusData
     * @return void
     */
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

    /**
     * Summary of fixOrderState
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return void
     */
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