<?php
declare(strict_types=1);

namespace Nofrixion\Payments\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\UrlInterface;
use Nofrixion\Payments\Helper\Data as NoFrixionHelper;
use Nofrixion\Payments\Model\Payment\Nofrixion;

class ConfigProvider implements ConfigProviderInterface
{

    private UrlInterface $url;
    private NoFrixionHelper $helper;

    public function __construct(UrlInterface $url, NoFrixionHelper $helper)
    {
        $this->url = $url;
        $this->helper = $helper;
    }

    public function getConfig()
    {
        $data = [
            'payment' => [
                Nofrixion::CODE => [
                    'initParams' => [
                        'createPaymentRequestUrl' => $this->url->getUrl('nofrixion/paymentRequest/create'),
                        'apiBaseUrl' => $this->helper->getApiBaseUrl()
                    ]
                ]
            ]
        ];
        return $data;
    }
}
