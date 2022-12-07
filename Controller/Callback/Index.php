<?php
declare(strict_types=1);

namespace Nofrixion\Payments\Controller\Callback;

use Magento\Framework\App\RequestInterface;

class Index implements \Magento\Framework\App\ActionInterface
{

    private \Nofrixion\Payments\Helper\Data $nofrixionHelper;
    private RequestInterface $request;
    private \Magento\Checkout\Model\Session $checkoutSession;
    private \Magento\Framework\UrlInterface $url;

    public function __construct(RequestInterface $request, \Nofrixion\Payments\Helper\Data $nofrixionHelper, \Magento\Checkout\Model\Session $checkoutSession, \Magento\Framework\UrlInterface $url)
    {
        $this->nofrixionHelper = $nofrixionHelper;
        $this->request = $request;
        $this->checkoutSession = $checkoutSession;
        $this->url = $url;
    }

    public function execute()
    {

        die('Callback page reached!');

//        $quoteId = $this->request->getParam('quote_id');
//        if ($quoteId) {
//            $quote = $this->checkoutSession->getQuote();
//            $storeId = $quote->getStoreId();
//
//            $amount = $quote->getGrandTotal();
//            $customerEmail = $quote->getCustomerEmail();
//            $currency = $quote->getQuoteCurrencyCode();
//            $originUrl = $this->url->getBaseUrl(['_store' => $storeId]);
//            $callbackUrl = $this->url->getBaseUrl('*/callback/index',['_store' => $storeId]);
//
//            $client = $this->nofrixionHelper->getPaymentRequestClient($storeId);
//            $client->createPaymentRequest($originUrl, $callbackUrl, $amount, $customerEmail, $currency);
//        } else {
//
//        }


    }
}
