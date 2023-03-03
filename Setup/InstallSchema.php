<?php

declare(strict_types=1);

namespace Nofrixion\Payments\Setup;

use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\StatusFactory;
use Magento\Sales\Model\ResourceModel\Order\Status as StatusResource;
use Magento\Sales\Model\ResourceModel\Order\StatusFactory as StatusResourceFactory;
use Nofrixion\Payments\Model\OrderStatuses;

class InstallSchema implements InstallSchemaInterface
{


    protected StatusFactory $statusFactory;
    protected StatusResourceFactory $statusResourceFactory;
    private ConfigInterface $configResource;

    public function __construct(ConfigInterface $configResource, StatusFactory $statusFactory, StatusResourceFactory $statusResourceFactory)
    {
        $this->configResource = $configResource;
        $this->statusFactory = $statusFactory;
        $this->statusResourceFactory = $statusResourceFactory;
    }

    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        // Add new order statuses and assign them to states
        $this->addNewStatusToState(Order::STATE_PENDING_PAYMENT, ['status' => OrderStatuses::STATUS_CODE_PENDING_PAYMENT, 'label' => OrderStatuses::STATUS_LABEL_PENDING_PAYMENT]);
        $this->addNewStatusToState(Order::STATE_PAYMENT_REVIEW, ['status' => OrderStatuses::STATUS_CODE_UNDERPAID, 'label' => OrderStatuses::STATUS_LABEL_UNDERPAID]);
        $this->addNewStatusToState(Order::STATE_PROCESSING, ['status' => OrderStatuses::STATUS_CODE_PAID_CORRECTLY, 'label' => OrderStatuses::STATUS_LABEL_PAID_CORRECTLY]);
        $this->addNewStatusToState(Order::STATE_PAYMENT_REVIEW, ['status' => OrderStatuses::STATUS_CODE_OVERPAID, 'label' => OrderStatuses::STATUS_LABEL_OVERPAID]);

        $installer->endSetup();
    }

    protected function addNewStatusToState($state, $statusData): void
    {
        /** @var StatusResource $statusResource */
        $statusResource = $this->statusResourceFactory->create();
        $status = $this->statusFactory->create();
        $status->setData($statusData);
        try {
            $statusResource->save($status);
        } catch (AlreadyExistsException $exception) {
            return;
        }
        $status->assignState($state, false, true);
    }

}
