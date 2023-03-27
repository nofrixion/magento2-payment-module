<?php
namespace Nofrixion\Payments\Block\Order;

/**
 * Sales order history block
 */
class History extends \Magento\Sales\Block\Order\History
{
    /**
     * @var string
     */
    protected $_template = 'Nofrixion/Payments::order/history.phtml';

    // should be sufficient to inherit constructor

    
    /**
     * Get order view URL
     *
     * @param object $order
     * @return string
     */
    public function getPaymentUrl($order)
    {
        return $this->getUrl('sales/order/view', ['pay_order' => $order->getId()]);
    }

}