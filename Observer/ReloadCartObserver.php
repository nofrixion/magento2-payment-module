<?php
namespace Nofrixion\Payments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Checkout\Model\Session as CheckoutSession;

class ReloadCartObserver implements ObserverInterface
{
    protected $checkoutSession;

    public function __construct(CheckoutSession $checkoutSession)
    {
        $this->checkoutSession = $checkoutSession;
    }

    public function execute(Observer $observer)
    {
        /*
        xdebug_break();

        $quote = $this->checkoutSession->getLastQuote();

        if ($quote && !$quote->getId()) {
            $quote->setIsActive(true)->save();
            $this->checkoutSession->replaceQuote($quote);
        }
        */
    }
}
