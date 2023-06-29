<?php

declare(strict_types=1);

namespace NoFrixion\Payments\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Sales\Model\Order;
use NoFrixion\Payments\Helper\Data as NoFrixionHelper;
use NoFrixion\Payments\Model\OrderStatuses;

class InstallSchema implements InstallSchemaInterface
{
    private NoFrixionHelper $helper;

    public function __construct(NoFrixionHelper $nofrixionHelper)
    {
        $this->helper = $nofrixionHelper;
    }

    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        // Add new order statuses and assign them to states
        $this->helper->addNewStatusToState(Order::STATE_PENDING_PAYMENT, ['status' => OrderStatuses::STATUS_CODE_PENDING_PAYMENT, 'label' => OrderStatuses::STATUS_LABEL_PENDING_PAYMENT]);
        $this->helper->addNewStatusToState(Order::STATE_PAYMENT_REVIEW, ['status' => OrderStatuses::STATUS_CODE_UNDERPAID, 'label' => OrderStatuses::STATUS_LABEL_UNDERPAID]);
        $this->helper->addNewStatusToState(Order::STATE_PROCESSING, ['status' => OrderStatuses::STATUS_CODE_PAID_CORRECTLY, 'label' => OrderStatuses::STATUS_LABEL_PAID_CORRECTLY]);
        $this->helper->addNewStatusToState(Order::STATE_PAYMENT_REVIEW, ['status' => OrderStatuses::STATUS_CODE_OVERPAID, 'label' => OrderStatuses::STATUS_LABEL_OVERPAID]);

        $installer->endSetup();
    }


}
