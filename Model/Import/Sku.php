<?php
/**
 * @author Ronak Patel
 * @package Raghu_SkuUpdate
 */
namespace Raghu\SkuUpdate\Model\Import;

use Raghu\SkuUpdate\Model\Import\Sku\RowValidatorInterface as ValidatorInterface;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;

class Sku extends \Magento\ImportExport\Model\Import\Entity\AbstractEntity
{
    const OLD_SKU = 'old_sku';

    const NEW_SKU = 'new_sku';

    const TABLE_ENTITY = 'catalog_product_entity';

    /**
     * Validation failure message template definitions
     *
     * @var array
     */
    protected $_messageTemplates = [
        ValidatorInterface::ERROR_INVALID_OLD_SKU => 'Old sku invalid',
        ValidatorInterface::ERROR_INVALID_NEW_SKU => 'New sku invalid',
        ValidatorInterface::ERROR_OLD_SKU_NOT_FOUND => 'Old sku not found in products',
        ValidatorInterface::ERROR_NEW_SKU_ALREADY_AVAILABLE => 'New sku already assigned to product',
        ValidatorInterface::ERROR_NEW_SKU_DUPLICATE => 'Duplicate SKU found in sheet'
    ];

    protected $_permanentAttributes = [self::OLD_SKU];

    /**
     * If we should check column names
     *
     * @var bool
     */
    protected $needColumnCheck = true;
    /**
     * Valid column names
     *
     * @array
     */
    protected $validColumnNames = [
        self::OLD_SKU,
        self::NEW_SKU
    ];
    /**
     * Need to log in import history
     *
     * @var bool
     */
    protected $logInHistory = true;

    protected $_validators = [];

    protected $_connection;

    protected $_resource;

    protected $_booleanOptions = array('0','1');

    protected $_productFactory;

    /**
     * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
     */
    public function __construct(
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\ImportExport\Helper\Data $importExportData,
        \Magento\ImportExport\Model\ResourceModel\Import\Data $importData,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper,
        \Magento\Framework\Stdlib\StringUtils $string,
        ProcessingErrorAggregatorInterface $errorAggregator,
        \Magento\Catalog\Api\Data\ProductInterfaceFactory $productFactory
    ) {
        $this->jsonHelper = $jsonHelper;
        $this->_importExportData = $importExportData;
        $this->_resourceHelper = $resourceHelper;
        $this->_dataSourceModel = $importData;
        $this->_resource = $resource;
        $this->_productFactory = $productFactory;
        $this->_connection = $resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);
        $this->errorAggregator = $errorAggregator;

        $this->_initErrorTemplates();
    }

    public function getValidColumnNames()
    {
        return $this->validColumnNames;
    }

    /**
     * Entity type code getter.
     *
     * @return string
     */
    public function getEntityTypeCode()
    {
        return 'raghu_skuupdate';
    }

    /**
     * Row validation.
     *
     * @param array $rowData
     * @param int $rowNum
     * @return bool
     */
    public function validateRow(array $rowData, $rowNum)
    {
        $title = false;

        if (isset($this->_validatedRows[$rowNum])) {
            return !$this->getErrorAggregator()->isRowInvalid($rowNum);
        }

        $this->_validatedRows[$rowNum] = true;

        if (\Magento\ImportExport\Model\Import::BEHAVIOR_APPEND == $this->getBehavior()) {

            $errorMessage = [];

            $oldSkuValidate = new \Magento\Framework\Validator();
            $oldSkuValidate->addValidator(new \Magento\Framework\Validator\NotEmpty());
            if (!isset($rowData[self::OLD_SKU]) || !$oldSkuValidate->isValid($rowData[self::OLD_SKU])) {
                $errorMessage[] = ValidatorInterface::ERROR_INVALID_OLD_SKU;
            }

            $newSkuValidate = new \Magento\Framework\Validator();
            $newSkuValidate->addValidator(new \Magento\Framework\Validator\NotEmpty());
            if (!isset($rowData[self::NEW_SKU]) || !$newSkuValidate->isValid($rowData[self::NEW_SKU])) {
                $errorMessage[] = ValidatorInterface::ERROR_INVALID_NEW_SKU;
            }

            if (!empty($errorMessage)) {
                $this->addRowError(implode(', ', $errorMessage), $rowNum);
                return false;
            }
        }

        return !$this->getErrorAggregator()->isRowInvalid($rowNum);
    }

    /**
     * Create Advanced price data from raw data.
     *
     * @throws \Exception
     * @return bool Result of operation.
     */
    protected function _importData()
    {

        $this->updateEntity();
        return true;
    }

    /**
     * Save and replace newsletter subscriber
     *
     * @return $this
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function updateEntity()
    {
        $tableName = $this->_resource->getTableName(self::TABLE_ENTITY);
        $behavior = $this->getBehavior();
        $listTitle = [];
        $new_sku_list = [];
        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            $entityList = [];
            foreach ($bunch as $rowNum => $rowData) {

                if (!$this->validateRow($rowData, $rowNum)) {
                    $this->addRowError(ValidatorInterface::ERROR_TITLE_IS_EMPTY, $rowNum);
                    continue;
                }

                if (in_array($rowData[self::NEW_SKU], $new_sku_list)) {
                    $this->addRowError(ValidatorInterface::ERROR_NEW_SKU_DUPLICATE, $rowNum);
                    continue;
                }

                array_push($new_sku_list, $rowData[self::NEW_SKU]);

                if ($this->isSkuAvailableforInsert($rowData[self::OLD_SKU])) {
                    /* Old Product not found in database */
                    $this->addRowError(ValidatorInterface::ERROR_OLD_SKU_NOT_FOUND, $rowNum);
                    continue;
                }

                if (!$this->isSkuAvailableforInsert($rowData[self::NEW_SKU])) {
                    /* New Product found in database */
                    $this->addRowError(ValidatorInterface::ERROR_NEW_SKU_ALREADY_AVAILABLE, $rowNum);
                    continue;
                }

                if ($this->getErrorAggregator()->hasToBeTerminated()) {
                    $this->getErrorAggregator()->addRowToSkip($rowNum);
                    continue;
                }

                $rowTtile= $rowData[self::OLD_SKU];
                $listTitle[] = $rowTtile;
                $entityList[$rowTtile][] = [
                    self::OLD_SKU => $rowData[self::OLD_SKU],
                    self::NEW_SKU => $rowData[self::NEW_SKU],
                    'rowNum' => $rowNum
                ];
            }

            foreach ($entityList as $key => $value) {

                $productCollection = $this->_productFactory->create()->getCollection();
                $productCollection->addAttributeToFilter('sku', $key);
                $product = $productCollection->getFirstItem();

                if (!empty($product->getData())) {

                    if ($this->isSkuAvailableforInsert($value[0][self::NEW_SKU])) {

                        $product_update = array('row_id'=>$product->getRowId(),'sku'=>$value[0][self::NEW_SKU]);
                        $this->_connection->insertOnDuplicate($tableName, $product_update, ['row_id','sku']);
                    } else {
                        $this->addRowError(ValidatorInterface::ERROR_NEW_SKU_ALREADY_AVAILABLE, $value[0]['rowNum']);
                    }

                } else {
                     $this->addRowError(ValidatorInterface::ERROR_OLD_SKU_NOT_FOUND, $value[0]['rowNum']);
                }
            }
        }
        return $this;
    }

    /**
     * Check sku already exist.
     *
     * @param string $sku
     * @return boolean
     */
    protected function isSkuAvailableforInsert($sku = null)
    {
        if ($sku) {

            $productCollection = $this->_productFactory->create()->getCollection();
            $productCollection->addAttributeToFilter('sku', $sku);

            $product = $productCollection->getFirstItem();

            if (empty($product->getData())) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Initialize Sku error templates
     */
    protected function _initErrorTemplates()
    {
        foreach ($this->_messageTemplates as $errorCode => $template) {
            $this->addMessageTemplate($errorCode, $template);
        }
    }
}
