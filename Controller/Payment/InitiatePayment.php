<?php

declare(strict_types=1);

namespace Nofrixion\Payments\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Api\OrderManagementInterface;
use Nofrixion\Payments\Helper\Data as NofrixionHelper;
use Nofrixion\Payments\Model\OrderStatuses;
use Nofrixion\Util\PreciseNumber;
use Psr\Log\LoggerInterface;

/**
 * InitiatePayment - controller that creates Magento Order, NoFrixion payment request and initializes payment. 
 * @todo add card handling
 * @todo add a logger.
 * @todo error handling around payment request creation.
 */
class InitiatePayment implements \Magento\Framework\App\ActionInterface
{
    private NofrixionHelper $nofrixionHelper;
    private CustomerSession $customerSession;
    private CookieManagerInterface $cookieManager;
    private CookieMetadataFactory $cookieMetadataFactory;
    private LoggerInterface $logger;
    private ManagerInterface $messageManager;
    private OrderManagementInterface $orderManager;
    private PageFactory $resultPageFactory;
    private QuoteFactory $quoteFactory;
    private RequestInterface $request;
    private SessionManagerInterface $sessionManager;
    private Session $checkoutSession;
    private RedirectFactory $resultRedirectFactory;

    public function __construct(
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory,
        CustomerSession $customerSession,
        LoggerInterface $logger,
        ManagerInterface $messageManager,
        NofrixionHelper $helper,
        OrderManagementInterface $orderManager,
        PageFactory $resultPageFactory,
        QuoteFactory $quoteFactory,
        RedirectFactory $resultRedirectFactory,
        RequestInterface $request,
        Session $checkoutSession,
        SessionManagerInterface $sessionManager
    ) {
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->customerSession = $customerSession;
        $this->logger = $logger;
        $this->messageManager = $messageManager;
        $this->nofrixionHelper = $helper;
        $this->orderManager = $orderManager;
        $this->request = $request;
        $this->resultPageFactory = $resultPageFactory;
        $this->quoteFactory = $quoteFactory;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->checkoutSession = $checkoutSession;
        $this->sessionManager = $sessionManager;
    }

    private function setCookie($name, $value, $duration)
    {
        $path = $this->sessionManager->getCookiePath();
        $domain = $this->sessionManager->getCookieDomain();

        $metadata = $this->cookieMetadataFactory->createPublicCookieMetadata()->setDuration($duration)->setPath($path)->setDomain($domain);

        $this->cookieManager->setPublicCookie($name, $value, $metadata);
    }

    /**
     * Summary of execute
     * @return \Magento\Framework\Controller\Result\Redirect|\Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        xdebug_break();

        $resultRedirect = $this->resultRedirectFactory->create();

        $nofrixionMessages = array();
        $bankId = rawurldecode($this->request->getParam('bankId'));

        $order = $this->checkoutSession->getLastRealOrder();
        if ($order && $order->getId()) {
            $paymentRequest = $this->nofrixionHelper->createPaymentRequest($order);

            $pendingPaymentStatus = OrderStatuses::STATUS_CODE_PENDING_PAYMENT;
            $order->addCommentToStatusHistory('Forwarded customer to payment page', $pendingPaymentStatus);
            $order->save();

            // Set cookies for the order/returns page
            $duration = 30 * 24 * 60 * 60;
            $this->setCookie('oar_order_id', $order->getIncrementId(), $duration);
            if (!$this->customerSession->isLoggedIn()) {
                $this->setCookie('oar_billing_lastname', $order->getBillingAddress()->getLastName(), $duration);
                $this->setCookie('oar_email', $order->getCustomerEmail(), $duration);
            }

            // need to call: https://api-sandbox.nofrixion.com/api/v1/paymentrequests/{id}/pisp
            //      with body field 'ProviderID' = $bankId
            // $amount = PreciseNumber::parseString((string) $paymentRequest['amount']);
            $paymentInitialization = $this->nofrixionHelper->initiatePayByBank($paymentRequest['id'], $bankId, null);

            // Can't handle exception caused by null URL in controller so check with 'if' and 'filter_var'
            if (filter_var($paymentInitialization->redirectUrl, FILTER_VALIDATE_URL)) {
                $resultRedirect->setUrl($paymentInitialization->redirectUrl);
                try {
                    // $resultRedirect used if forwarding to PIS provider
                    return $resultRedirect;
                } catch (Exception $e) {
                    $this->logger->error('Error initializing payment.', ['exception' => $e]);
                    $this->initializationFailureAction($order, $paymentRequest, 'Order cancelled due to payment initialization error.');
                }

            } else {
                // or $paymentInitialization->redirectUrl is invalid.
                $msg = 'A valid payment URL was not received for provider ' . $bankId;
                $this->logger->error($msg);
                $this->initializationFailureAction($order, $paymentRequest, $msg);
            }
        } else {
            // If we get to here $order was not valid
            $this->messageManager->addWarningMessage('An error occurred creating your order.');
        }
        // if we haven't successfully directed to the payment URL return to cart.
        $resultRedirect->setPath('checkout/cart');
        return $resultRedirect;
    }

    public function initializationFailureAction($order, $paymentRequest, $message){
        // restore cart
        $this->restoreCart($order);
        $this->messageManager->addWarningMessage($message);

        // cancel order
        $this->orderManager->cancel($order->getId());
        $order->setStatus(Order::STATE_CANCELED);
        $order->addStatusToHistory(Order::STATE_CANCELED, '', false);
        $order->save();

        // delete payment request

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
        }
    }
}