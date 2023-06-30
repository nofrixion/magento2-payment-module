<?php

declare(strict_types=1);

namespace Nofrixion\Payments\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\View\Result\PageFactory;
use Nofrixion\Payments\Helper\Data as NofrixionHelper;
use Nofrixion\Payments\Model\OrderStatuses;

class Submit implements \Magento\Framework\App\ActionInterface
{

    private NofrixionHelper $nofrixionHelper;
    private CustomerSession $customerSession;
    private CookieManagerInterface $cookieManager;
    private CookieMetadataFactory $cookieMetadataFactory;
    private PageFactory $resultPageFactory;
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
        Session $checkoutSession,
        SessionManagerInterface $sessionManager
    ) {
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->customerSession = $customerSession;
        $this->nofrixionHelper = $helper;
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
        $order = $this->checkoutSession->getLastRealOrder();

        if ($order && $order->getId()) {
            $paymentRequest = $this->nofrixionHelper->createPaymentRequest($order);

            $pendingPaymentStatus = OrderStatuses::STATUS_CODE_PENDING_PAYMENT;
            $order->addCommentToStatusHistory('Forwarding customer to the hosted payment page', $pendingPaymentStatus);
            $order->save();

            if (!$this->customerSession->isLoggedIn()) {
                // Set cookies for the order/returns page
                $duration = 30 * 24 * 60 * 60;
                $this->setCookie('oar_order_id', $order->getIncrementId(), $duration);
                $this->setCookie('oar_billing_lastname', $order->getBillingAddress()->getLastName(), $duration);
                $this->setCookie('oar_email', $order->getCustomerEmail(), $duration);
            }
            // Send payment request and order details to SubmitPayment block and return the payments page
            $resultPage = $this->resultPageFactory->create();
            $resultPage->getLayout()->getBlock('submit_payment')->setData('paymentRequest', $paymentRequest);
            $resultPage->getLayout()->getBlock('submit_payment')->setData('order', $order);
            return $resultPage;
        } else {
            // Send back to the cart page
            $resultRedirect = $this->resultRedirectfactory->create();
            $resultRedirect->setUrl($order->getStore()->getUrl('checkout/cart'));
            return $resultRedirect;
        }
    }


}