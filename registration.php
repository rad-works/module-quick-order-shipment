<?php
declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

/**
 * @TODO refactor: skip deduction by sku
 */
ComponentRegistrar::register(ComponentRegistrar::MODULE, 'RadWorks_QuickOrderShipment', __DIR__);
