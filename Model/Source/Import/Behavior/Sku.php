<?php
/**
 * @author Ronak Patel
 * @package Raghu_SkuUpdate
 */
namespace Raghu\SkuUpdate\Model\Source\Import\Behavior;

/**
 * Import behavior source model
 *
 * @api
 * @since 100.0.2
 */
class Sku extends \Magento\ImportExport\Model\Source\Import\AbstractBehavior
{
    /**
     * {@inheritdoc}
     */
    public function toArray()
    {
        return [
            \Magento\ImportExport\Model\Import::BEHAVIOR_ADD_UPDATE => __('Update only')
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getCode()
    {
        return 'raghu_skuupdate_update_only';
    }
}
