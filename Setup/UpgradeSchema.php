<?php

namespace Nofrixion\Payments\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Sales\Model\Order;
use Nofrixion\Payments\Model\OrderStatuses;

class UpgradeSchema implements UpgradeSchemaInterface
{
    private \Nofrixion\Payments\Helper\Data $helper;

    public function __construct(\Nofrixion\Payments\Helper\Data $nofrixionHelper)
    {
        $this->helper = $nofrixionHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        if (version_compare($context->getVersion(), "1.0.3", "<")) {
            $this->helper->addNewStatusToState(Order::STATE_PENDING_PAYMENT, ['status' => OrderStatuses::STATUS_CODE_AUTHORIZED_PAYMENT, 'label' => OrderStatuses::STATUS_LABEL_AUTHORIZED_PAYMENT]);
        }
        $setup->endSetup();
    }
}
