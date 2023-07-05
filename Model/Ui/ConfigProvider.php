<?php
declare(strict_types=1);

namespace Nofrixion\Payments\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\UrlInterface;
use Nofrixion\Payments\Helper\Data as NofrixionHelper;
use Nofrixion\Payments\Model\Payment\Nofrixion;

class ConfigProvider implements ConfigProviderInterface
{

    private NofrixionHelper $helper;
    private UrlInterface $url;

    public function __construct(
        NofrixionHelper $helper,
        UrlInterface $url
    ) {
        $this->helper = $helper;
        $this->url = $url;
    }

    public function getConfig()
    {
        $data = [
            'payment' => [
                Nofrixion::CODE => [
                    // 'paymentRedirectUrl' => $this->url->getUrl('nofrixion/redirect/forwardToPayment', [
                    'paymentRedirectUrl' => $this->url->getUrl('nofrixion/payment/submit', [
                        '_secure' => true,
                        '_nosid' => true
                    ]),
                    'pispProviders' => $this->helper->getPayByBankSettings()
                ]
            ]
        ];
        return $data;
    }
}