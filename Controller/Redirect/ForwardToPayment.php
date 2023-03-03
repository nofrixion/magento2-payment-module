<?php

declare(strict_types=1);

namespace Nofrixion\Payments\Controller\Redirect;

use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Nofrixion\Payments\Helper\Data as NoFrixionHelper;
use Nofrixion\Payments\Model\OrderStatuses;

class ForwardToPayment implements \Magento\Framework\App\ActionInterface
{

    private NoFrixionHelper $nofrixionHelper;
    private CustomerSession $customerSession;
    private CookieManagerInterface $cookieManager;
    private CookieMetadataFactory $cookieMetadataFactory;
    private SessionManagerInterface $sessionManager;
    private Session $checkoutSession;
    private RedirectFactory $resultRedirectfactory;

    public function __construct(RedirectFactory $resultRedirectFactory, Session $checkoutSession, CookieManagerInterface $cookieManager, CookieMetadataFactory $cookieMetadataFactory, SessionManagerInterface $sessionManager, NoFrixionHelper $helper, CustomerSession $customerSession)
    {
        $this->resultRedirectfactory = $resultRedirectFactory;
        $this->checkoutSession = $checkoutSession;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->cookieManager = $cookieManager;
        $this->sessionManager = $sessionManager;
        $this->nofrixionHelper = $helper;
        $this->customerSession = $customerSession;
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
        $resultRedirect = $this->resultRedirectfactory->create();

        if ($order && $order->getId()) {
            $paymentRequest = $this->nofrixionHelper->createPaymentRequest($order);
            $url = $paymentRequest['hostedPayCheckoutUrl'];

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

            $resultRedirect->setUrl($url);
        } else {
            // Send back to the cart page
            $resultRedirect->setUrl($order->getStore()->getUrl('checkout/cart'));
        }
        return $resultRedirect;
    }


}
