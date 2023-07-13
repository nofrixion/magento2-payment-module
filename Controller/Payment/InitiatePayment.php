<?php

declare(strict_types=1);

namespace Nofrixion\Payments\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\View\Result\PageFactory;
use Nofrixion\Payments\Helper\Data as NofrixionHelper;
use Nofrixion\Util\PreciseNumber;

/**
 * InitiatePayment - controller that creates Magento Order, NoFrixion payment request and initialises payment. 
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
    private PageFactory $resultPageFactory;
    private RequestInterface $request;
    private SessionManagerInterface $sessionManager;
    private Session $checkoutSession;
    private RedirectFactory $resultRedirectfactory;

    /**
     * Summary of __construct
     * @param \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager
     * @param \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Nofrixion\Payments\Helper\Data $helper
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Magento\Framework\Controller\Result\RedirectFactory $resultRedirectFactory
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Framework\Session\SessionManagerInterface $sessionManager
     */
    public function __construct(
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory,
        CustomerSession $customerSession,
        NofrixionHelper $helper,
        PageFactory $resultPageFactory,
        RedirectFactory $resultRedirectFactory,
        RequestInterface $request,
        Session $checkoutSession,
        SessionManagerInterface $sessionManager
    ) {
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->customerSession = $customerSession;
        $this->nofrixionHelper = $helper;
        $this->request = $request;
        $this->resultPageFactory = $resultPageFactory;
        $this->resultRedirectfactory = $resultRedirectFactory;
        $this->checkoutSession = $checkoutSession;
        $this->sessionManager = $sessionManager;
    }

    /**
     * Summary of setCookie
     * @param mixed $name
     * @param mixed $value
     * @param mixed $duration
     * @return void
     */
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

        $nofrixionMessages = array();
        $order = $this->checkoutSession->getLastRealOrder();
        $bankId = rawurldecode($this->request->getParam('bankId'));

        if ($order && $order->getId()) {
            $paymentRequest = $this->nofrixionHelper->createPaymentRequest($order);

            // $pendingPaymentStatus = OrderStatuses::STATUS_CODE_PENDING_PAYMENT;
            // $order->addCommentToStatusHistory('Forwarding customer to the hosted payment page', $pendingPaymentStatus);
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
            $amount = PreciseNumber::parseString((string) $paymentRequest['amount']);
            $paymentInitialization = $this->nofrixionHelper->initiatePayByBank($paymentRequest['id'], $bankId, null);

            // Exception thrown in setUrl doesn't seem to bubble back to controller so check with 'if' and 'filter_var'
            if (filter_var($paymentInitialization->redirectUrl, FILTER_VALIDATE_URL)) {
                // $resultRedirect used if forwarding to PIS provider
                $resultRedirect = $this->resultRedirectfactory->create();
                $resultRedirect->setUrl($paymentInitialization->redirectUrl);
                try {
                    return $resultRedirect;
                } catch (Exception $e) {
                    array_push($nofrixionMessages, $e->getMessage());
                }
            } else {
                array_push($nofrixionMessages, 'A valid payment URL was not received for provider: ' . $bankId);
            }
        }
        // If we get to here there was no valid order or $paymentInitialization->redirectUrl is invalid
        // so display result page with something descriptive on it.
        $resultPage = $this->resultPageFactory->create();
        try {
            $resultPage->getLayout()->getBlock('payment.result')->setData('paymentRequest', $paymentRequest);
            $resultPage->getLayout()->getBlock('payment.result')->setData('order', $order);
        } catch (Exception $e) {
            array_push($nofrixionMessages, $e->getMessage());
        }
        $resultPage->getLayout()->getBlock('payment.result')->setData('messages', $nofrixionMessages);
        return $resultPage;
    }
}