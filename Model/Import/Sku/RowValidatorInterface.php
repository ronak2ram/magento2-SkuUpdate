<?php
/**
 * @author Ronak Patel
 * @package Raghu_SkuUpdate
 */
namespace Raghu\SkuUpdate\Model\Import\Sku;

interface RowValidatorInterface extends \Magento\Framework\Validator\ValidatorInterface
{
    const ERROR_INVALID_OLD_SKU = 'invalidOldSku';

    const ERROR_INVALID_NEW_SKU = 'invalidNewSku';

    const ERROR_NEW_SKU_ALREADY_AVAILABLE = 'existNewSku';

  	const ERROR_NEW_SKU_DUPLICATE = 'duplicateSku';

    /**
     * Initialize validator
     *
     * @return $this
     */
    public function init($context);
}
