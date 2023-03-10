<?php
declare(strict_types=1);

namespace Nofrixion\Payments\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\UrlInterface;
use Nofrixion\Payments\Model\Payment\Nofrixion;

class ConfigProvider implements ConfigProviderInterface
{

    private UrlInterface $url;

    public function __construct(UrlInterface $url)
    {
        $this->url = $url;
    }

    public function getConfig()
    {
        $data = [
            'payment' => [
                Nofrixion::CODE => [
                    'paymentRedirectUrl' => $this->url->getUrl('nofrixion/redirect/forwardToPayment', [
                        '_secure' => true,
                        '_nosid' => true
                    ])
                ]
            ]
        ];
        return $data;
    }
}
