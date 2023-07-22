<?php

declare(strict_types=1);

namespace Nofrixion\Payments\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Model\Order;
use Nofrixion\Payments\Helper\Data as NofrixionHelper;
use Nofrixion\Payments\Model\OrderStatuses;
use Nofrixion\Util\PreciseNumber;
use Psr\Log\LoggerInterface;

/**
 * InitiatePayment - controller that creates Magento Order, NoFrixion payment request and initializes payment. 
 * @todo add card handling
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
    private PageFactory $resultPageFactory;
    private RedirectFactory $resultRedirectFactory;
    private RequestInterface $request;
    private SessionManagerInterface $sessionManager;
    private Session $checkoutSession;
    private UrlInterface $url;

    public function __construct(
        CustomerSession $customerSession,
        LoggerInterface $logger,
        ManagerInterface $messageManager,
        NofrixionHelper $helper,
        PageFactory $resultPageFactory,
        RedirectFactory $resultRedirectFactory,
        RequestInterface $request,
        Session $checkoutSession,
        SessionManagerInterface $sessionManager,
        UrlInterface $url
    ) {
        $this->customerSession = $customerSession;
        $this->logger = $logger;
        $this->messageManager = $messageManager;
        $this->nofrixionHelper = $helper;
        $this->request = $request;
        $this->resultPageFactory = $resultPageFactory;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->checkoutSession = $checkoutSession;
        $this->sessionManager = $sessionManager;
        $this->url = $url;
    }

    /**
     * Summary of execute
     * @return \Magento\Framework\Controller\Result\Redirect|\Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        //xdebug_break();

        $resultRedirect = $this->resultRedirectFactory->create();
        $bankId = rawurldecode($this->request->getParam('bankId'));

        $order = $this->checkoutSession->getLastRealOrder();
        if ($order && $order->getId()) {
            $paymentRequest = $this->nofrixionHelper->createPaymentRequest($order);

            $pendingPaymentStatus = OrderStatuses::STATUS_CODE_PENDING_PAYMENT;
            $order->addCommentToStatusHistory('Forwarded customer to payment page', $pendingPaymentStatus);
            $order->save();

            // need to call: https://api-sandbox.nofrixion.com/api/v1/paymentrequests/{id}/pisp
            //      with body field 'ProviderID' = $bankId
            // $amount = PreciseNumber::parseString((string) $paymentRequest['amount']);
            $failureRedirectUrl = $this->url->getUrl('checkout/cart', ['_secure' => true]);
            $paymentInitialization = $this->nofrixionHelper->initiatePayByBank($paymentRequest['id'], $bankId, $failureRedirectUrl, null);

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
        $this->nofrixionHelper->restoreCart($order);
        $this->messageManager->addWarningMessage($message);

        // Can't delete payment request as it has events (pisp_initiate).
        // $this->nofrixionHelper->deletePaymentRequest($paymentRequest['id']);
    }
}