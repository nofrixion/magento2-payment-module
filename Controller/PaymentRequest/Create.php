<?php
declare(strict_types=1);

namespace Nofrixion\Payments\Controller\PaymentRequest;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use NoFrixion\Util\PreciseNumber;
use Psr\Log\LoggerInterface;

class Create implements \Magento\Framework\App\ActionInterface
{

    private \Nofrixion\Payments\Helper\Data $nofrixionHelper;
    private RequestInterface $request;
    private \Magento\Checkout\Model\Session $checkoutSession;
    private \Magento\Framework\UrlInterface $url;
    private JsonFactory $resultFactory;

    public function __construct(LoggerInterface $logger, RequestInterface $request, \Nofrixion\Payments\Helper\Data $nofrixionHelper, \Magento\Checkout\Model\Session $checkoutSession, \Magento\Framework\UrlInterface $url, JsonFactory $resultFactory)
    {
        $this->nofrixionHelper = $nofrixionHelper;
        $this->request = $request;
        $this->checkoutSession = $checkoutSession;
        $this->url = $url;
        $this->resultFactory = $resultFactory;
        $this->logger = $logger;
    }

    public function execute()
    {
        $quote = $this->checkoutSession->getQuote();
        $storeId = $quote->getStoreId();

        $amount = $quote->getGrandTotal();
        $customerEmail = $quote->getCustomerEmail();
        $currency = $quote->getQuoteCurrencyCode();
        $originUrl = $this->url->getBaseUrl(['_store' => $storeId]);
        $callbackUrl = $this->url->getUrl('nofrixion/callback/index', ['_secure' => true]);
        $amount = PreciseNumber::parseString((string)$amount);
        $quoteId = $quote->getId();
        if ($quote->getCustomerId()) {
            $customerId = (string)$quote->getCustomerId();
        } else {
            $customerId = null;
        }

        $createCardToken = true;

        $client = $this->nofrixionHelper->getPaymentRequestClient($storeId);
        $r = $this->resultFactory->create();

        $originUrl = str_replace('http://', 'https://', $originUrl);
        $nofrixionOrderId = $quoteId . '-' . date('Y-m-d H:i:s');

        try {
            $paymentRequest = $client->createPaymentRequest($originUrl, $callbackUrl, $amount, $customerEmail, $currency, null, $nofrixionOrderId, $createCardToken, $customerId);

            $r->setHttpResponseCode(201);
            $r->setJsonData(json_encode(['id' => $paymentRequest['id']], JSON_THROW_ON_ERROR));

        } catch (\Throwable $t) {
            $this->logger->error($t);

            $r->setHttpResponseCode(500);
            $r->setJsonData(json_encode(['error_msg' => $t->getMessage()], JSON_THROW_ON_ERROR));
        }
        return $r;
    }
}
