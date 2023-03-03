<?php

declare(strict_types=1);

namespace Nofrixion\Payments\Controller\Webhook;

use Magento\Framework\App\ActionInterface;
use Nofrixion\Payments\Helper\Data as NoFrixionHelper;

class In implements ActionInterface
{

    private NoFrixionHelper $nofrixionHelper;

    public function __construct(NoFrixionHelper $helper)
    {
        $this->nofrixionHelper = $helper;
    }

    public function execute()
    {
        $paymentRequestId = $this->request->getParam('id');
        if ($paymentRequestId) {
            $this->nofrixionHelper->processPayment($paymentRequestId);
        } else {
            throw new \RuntimeException('Missing parameter "id"');
        }
    }
}
