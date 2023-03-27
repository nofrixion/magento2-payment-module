<?php

declare(strict_types=1);

namespace Nofrixion\Payments\Model;

class OrderStatuses extends \Magento\Framework\Model\AbstractModel
{

    const STATUS_CODE_UNDERPAID = 'nofrixion_underpaid';
    const STATUS_LABEL_UNDERPAID = 'Underpaid with Nofrixion';

    const STATUS_CODE_PAID_CORRECTLY = 'nofrixion_paid_correctly';
    const STATUS_LABEL_PAID_CORRECTLY = 'Paid using Nofrixion';
//
    const STATUS_CODE_OVERPAID = 'nofrixion_overpaid';
    const STATUS_LABEL_OVERPAID = 'Overpaid with Nofrixion';

    const STATUS_CODE_PENDING_PAYMENT = 'nofrixion_pending_payment';
    const STATUS_LABEL_PENDING_PAYMENT = 'Pending Nofrixion payment';

    const STATUS_CODE_AUTHORIZED_PAYMENT = 'nofrixion_authorized_payment';
    const STATUS_LABEL_AUTHORIZED_PAYMENT = 'Authorized Nofrixion payment';


}
