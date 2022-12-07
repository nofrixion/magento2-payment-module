<?php
declare(strict_types=1);

namespace Nofrixion\Payments\Helper;

use NoFrixion\Client\PaymentRequest;

class Data
{
    private \Magento\Framework\App\State $appState;
    private \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig;

    public function __construct(
        \Magento\Framework\App\State                       $appState,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
    )
    {
        $this->appState = $appState;
        $this->scopeConfig = $scopeConfig;
    }

    public function getPaymentRequestClient(?int $storeId = null): PaymentRequest
    {
        if ($this->isProductionMode($storeId)) {
            $apiToken = $this->scopeConfig->getValue('payment/nofrixion/api_token_production', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
        } else {
            $apiToken = $this->scopeConfig->getValue('payment/nofrixion/api_token_sandbox', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
        }
        $baseUrl = $this->getApiBaseUrl();
        $client = new PaymentRequest($baseUrl, $apiToken);
        return $client;
    }

    public function isProductionMode(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag('payment/nofrixion/is_production', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getApiBaseUrl(): string
    {
        if ($this->isProductionMode()) {
            return 'https://api.nofrixion.com';
        } else {
            return 'https://api-sandbox.nofrixion.com';
        }
    }


}
