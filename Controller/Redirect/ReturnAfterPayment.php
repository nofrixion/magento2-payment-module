<?php

declare(strict_types=1);

namespace Nofrixion\Payments\Controller\Redirect;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Model\StoreManagerInterface;
use Nofrixion\Payments\Helper\Data;
use Psr\Log\LoggerInterface;
use Storefront\BTCPay\Controller\Cart\Restore;

class ReturnAfterPayment implements \Magento\Framework\App\ActionInterface
{
    protected PageFactory $resultPageFactory;
    private Session $checkoutSession;
    private UrlInterface $url;
    private RequestInterface $request;
    private Data $nofrixionHelper;
    private LoggerInterface $logger;
    private StoreManagerInterface $storeManager;
    private RedirectFactory $resultRedirectFactory;

    public function __construct(StoreManagerInterface $storeManager, RequestInterface $request, LoggerInterface $logger, UrlInterface $url, RedirectFactory $resultRedirectFactory, PageFactory $resultPageFactory, Data $nofrixionHelper, OrderFactory $orderFactory, Session $checkoutSession, Restore $cartRestorer)
    {
        $this->storeManager = $storeManager;
        $this->request = $request;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->url = $url;
        $this->nofrixionHelper = $nofrixionHelper;
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
    }

    /**
     * Execute view action
     *
     * @return ResultInterface
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        $nofrixionOrderId = $this->request->getParam('nofrixion_order_id');
        $resultRedirect = $this->resultRedirectFactory->create();
        $store = $this->storeManager->getStore();

        try {
            $paymentRequest = $this->nofrixionHelper->getPaymentRequestByOrderId($nofrixionOrderId);
            $order = $this->nofrixionHelper->processPayment($paymentRequest['id'], (int)$store->getId());

            $this->checkoutSession->setLastQuoteId($order->getQuoteId());
            $this->checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
            $this->checkoutSession->setLastOrderId($order->getId());
            $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
            $resultRedirect->setUrl($order->getStore()->getUrl('checkout/onepage/success'));
        } catch (\Throwable $t) {
            $this->logger->error($t);
            // TODO what should we do? Restore the cart? Not sure...
            $resultRedirect->setUrl($this->url->getUrl('checkout/cart/'));
        }

        return $resultRedirect;
    }
}
