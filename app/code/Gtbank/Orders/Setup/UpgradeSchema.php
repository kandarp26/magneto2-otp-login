<?php 
namespace Gtbank\Orders\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;

class UpgradeSchema implements UpgradeSchemaInterface
{
	
    /**
     * {@inheritdoc}
     */
    public function upgrade(
        SchemaSetupInterface $setup,
        ModuleContextInterface $context
    ) {
        $installer = $setup;
		$installer->startSetup();
		
		
		if (version_compare($context->getVersion(), '1.0.2', '<')) {
			$installer->getConnection()->addColumn('sales_order', 'order_number', [
				'type' => 'text',
				'nullable' => true,
				'length' => '255',
				'comment' => 'order_number',
                ]
			);
		}
		
		if (version_compare($context->getVersion(), '1.0.3', '<')) {
			$installer->getConnection()->addColumn('sales_order_item', 'cancel_transaction_id', [
				'type' => 'text',
				'nullable' => true,
				'length' => '255',
				'comment' => 'cancel_transaction_id',
                ]
			);
		}
		
		if (version_compare($context->getVersion(), '1.0.4', '<')) {
			$installer->getConnection()->addColumn('sales_order', 'payment_mode', [
				'type' => 'text',
				'nullable' => true,
				'length' => '255',
				'comment' => 'payment_mode',
                ]
			);
		}

		
		if (version_compare($context->getVersion(), '1.0.5', '<')) {
			$installer->getConnection()->addColumn('quote_item', 'old_item_id', [
				'type' => Table::TYPE_INTEGER,
				'nullable' => true,
				'length' => '255',
				'comment' => 'old_item_id',
                ]
			);
		}
		
	}
}