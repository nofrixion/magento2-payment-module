<?php
namespace Nofrixion\Payments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Nofrixion\Payments\Helper\Data as NofrixionHelper;
use Nofrixion\Payments\Model\OrderStatuses;

class ReloadCartObserver implements ObserverInterface
{
    protected $checkoutSession;
    private ManagerInterface $messageManager;
    private NofrixionHelper $nofrixionHelper;

    public function __construct(
        CheckoutSession $checkoutSession,
        ManagerInterface $messageManager,        
        NofrixionHelper $helper,)
    {
        $this->checkoutSession = $checkoutSession;
        $this->messageManager = $messageManager;
        $this->nofrixionHelper = $helper;
    }

    public function execute(Observer $observer)
    {
        // xdebug_break();

        $message = 'Restored cart from abandoned or failed payment attempt. Please check your order and retry checkout.';
        $order = $this->checkoutSession->getLastRealOrder();
        $status = $order->getStatus();

        // Comparing against a specific status code
        if ($status == OrderStatuses::STATUS_CODE_PENDING_PAYMENT) {
            $this->nofrixionHelper->restoreCart($order);
            $this->messageManager->addWarningMessage($message);
        }
    }
}
