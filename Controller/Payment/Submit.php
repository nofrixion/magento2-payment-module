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
use Nofrixion\Model\PaymentRequests\PaymentInitiationResponse;
use Nofrixion\Payments\Helper\Data as NofrixionHelper;
use Nofrixion\Payments\Model\OrderStatuses;
use Nofrixion\Util\PreciseNumber;

class Submit implements \Magento\Framework\App\ActionInterface
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

    private function setCookie($name, $value, $duration)
    {
        $path = $this->sessionManager->getCookiePath();
        $domain = $this->sessionManager->getCookieDomain();

        $metadata = $this->cookieMetadataFactory->createPublicCookieMetadata()->setDuration($duration)->setPath($path)->setDomain($domain);

        $this->cookieManager->setPublicCookie($name, $value, $metadata);
    }

    public function execute()
    {

        xdebug_break();
        
        $resultRedirect = $this->resultRedirectfactory->create();
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
            $resultRedirect->setUrl($paymentInitialization->redirectUrl);
        } else {
            // Send back to the cart page
            $resultRedirect->setUrl($order->getStore()->getUrl('checkout/cart'));
        }
        return $resultRedirect;
    }


}