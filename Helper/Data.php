<?php
/**
 * Pronko Consulting
 *
 * @category  PronkoConsulting
 * @package   PronkoConsulting\ModuleName
 * @copyright 2020 Pronko Consulting
 * @license   Open Software License (OSL 3.0)
 * @link      https://www.pronkoconsulting.com/
 */

namespace PronkoConsulting\ModuleName\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Customer\Model\Session;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Framework\App\ResourceConnection;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Catalog\Model\ProductFactory;
use Magento\Checkout\Model\Cart;
use Vendor\Marketplace\Model\ProductFactory as MarketplaceProductFactory;
use Vendor\MpAssignProduct\Model\ItemsFactory;
use Vendor\MpAssignProduct\Model\DataFactory;
use Vendor\MpAssignProduct\Model\AssociatesFactory;
use Magento\Quote\Model\Quote\Item\OptionFactory;
use Magento\Framework\Registry;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Catalog\Model\Product\Option;
use Vendor\AppointedAttributes\Helper\Validation;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use Magento\Catalog\Api\ProductRepositoryInterface;

/**
 * Helper Data
 */
class Data extends \Vendor\MpAssignProduct\Helper\Data
{
    /**
     * @var Validation
     */
    protected $validationHelper;
    
    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var array
     */
    protected $skipAttributes = ['price', 'quantity_and_stock_status'];

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * Constructor
     *
     * @param Context                    $context
     * @param StoreManagerInterface      $storeManager
     * @param ManagerInterface           $messageManager
     * @param Session                    $customerSession
     * @param CustomerFactory            $customer
     * @param Filesystem                 $filesystem
     * @param FormKey                    $formKey
     * @param PricingHelper              $currency
     * @param ResourceConnection         $resource
     * @param UploaderFactory            $fileUploaderFactory
     * @param ProductFactory             $productFactory
     * @param Cart                       $cart
     * @param MarketplaceProductFactory  $mpProductFactory
     * @param ItemsFactory               $itemsFactory
     * @param DataFactory                $dataFactory
     * @param AssociatesFactory          $associatesFactory
     * @param OptionFactory              $quoteOption
     * @param CollectionFactory          $mpProductCollectionFactory
     * @param SellerCollection           $sellerCollectionFactory
     * @param ItemsCollection            $itemsCollectionFactory
     * @param QuoteCollection            $quoteCollectionFactory
     * @param DataCollection             $dataCollectionFactory
     * @param ProductCollection          $productCollectionFactory
     * @param Registry                   $coreRegistry
     * @param StockRegistryInterface     $stockRegistry
     * @param TransportBuilder           $transportBuilder
     * @param StateInterface             $inlineTranslation
     * @param PriceCurrencyInterface     $priceCurrency
     * @param FileDriver                 $fileDriver
     * @param ConfigurableCollection     $configurableCollection
     * @param Option                     $customOptions
     * @param Validation                 $validation
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        ManagerInterface $messageManager,
        Session $customerSession,
        CustomerFactory $customer,
        Filesystem $filesystem,
        FormKey $formKey,
        PricingHelper $currency,
        ResourceConnection $resource,
        UploaderFactory $fileUploaderFactory,
        ProductFactory $productFactory,
        Cart $cart,
        MarketplaceProductFactory $mpProductFactory,
        ItemsFactory $itemsFactory,
        DataFactory $dataFactory,
        AssociatesFactory $associatesFactory,
        OptionFactory $quoteOption,
        CollectionFactory $mpProductCollectionFactory,
        SellerCollection $sellerCollectionFactory,
        ItemsCollection $itemsCollectionFactory,
        QuoteCollection $quoteCollectionFactory,
        DataCollection $dataCollectionFactory,
        ProductCollection $productCollectionFactory,
        Registry $coreRegistry,
        StockRegistryInterface $stockRegistry,
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        PriceCurrencyInterface $priceCurrency,
        FileDriver $fileDriver,
        ConfigurableCollection $configurableCollection,
        Option $customOptions,
        Validation $validation,
        ProductRepositoryInterface $productRepository
    ) {
        parent::__construct(
            $context,
            $storeManager,
            $messageManager,
            $customerSession,
            $customer,
            $filesystem,
            $formKey,
            $currency,
            $resource,
            $fileUploaderFactory,
            $productFactory,
            $cart,
            $mpProductFactory,
            $itemsFactory,
            $dataFactory,
            $associatesFactory,
            $quoteOption,
            $mpProductCollectionFactory,
            $sellerCollectionFactory,
            $itemsCollectionFactory,
            $quoteCollectionFactory,
            $dataCollectionFactory,
            $productCollectionFactory,
            $coreRegistry,
            $stockRegistry,
            $transportBuilder,
            $inlineTranslation,
            $priceCurrency,
            $fileDriver,
            $configurableCollection,
            $customOptions
        );

        $this->validationHelper = $validation;
        $this->resourceConnection = $resource;
        $this->productRepository = $productRepository;
    }

    /**
     * Validate Data
     *
     * @param  array  $data
     * @param  string $type
     * @return array
     */
    public function validateData($data, $type)
    {
        if ($type == ConfigurableType::TYPE_CODE) {
            return $this->validateConfigData($data);
        }

        $result = [];
        $requiredFields = $this->validationHelper->getRequiredFields();
        $compareResult = array_diff_key($requiredFields, $data);

        if (count($compareResult)) {
            $result['error'] = true;
            reset($compareResult);
            $result['msg'] = __('%1 is required field', ucfirst(key($compareResult)));
            return $result;
        }

        $rules = $this->validationHelper->getAttributeRules();
        $isSuccess = true;

        foreach ($data as $field => $value) {
            if (isset($rules[$field])) {
                foreach ($rules[$field] as $ruleCode => $ruleStatus) {
                    $ruleParts = explode('-', $ruleCode);
                    $rule = '';
                    foreach ($ruleParts as $rulePart) {
                        $rule .= $rule
                            ? ucfirst($rulePart)
                            : $rulePart;
                    }
                    if (is_callable([$this->validationHelper, $rule])) {
                        $validationStatus = $this->validationHelper->$rule($value);
                        $result[$field][$ruleCode] = $validationStatus;
                        if (!$validationStatus) {
                            $isSuccess = false;
                        }
                    }
                }
            }
        }

        $found = $this->validateAvailibility($data);
        if ($found) {
            $result['error'] = true;
            $result['msg'] =  __('You already have same product with same attributes.');
            $isSuccess = false;
        }

        $result['error'] = !$isSuccess;
        return $result;
    }

    /**
     * Validate availibility
     *
     * @param  array $data
     * @return bool
     */
    private function validateAvailibility($data)
    {
        $productId = $data['product_id'];
        $assignId = 0;
        if (isset($data['assign_id'])) {
            $assignId = $data['assign_id'];
        }

        $assigned = $this->getCollection()
            ->addFieldToFilter('product_id', ['eq' => $productId])
            ->addFieldToFilter('entity_id', ['neq' => $assignId])
            ->addFieldToFilter('seller_id', ['eq' => $this->getCustomerId()]);
        $found = false;
        $allowedAttributes = $this->getAllowedAttributes($this->getProduct($productId));
        if ($assigned->getSize()) {
            foreach ($assigned as $item) {
                reset($allowedAttributes);
                $attributes = 0;
                foreach ($allowedAttributes as $attribute) {
                    if (!isset($data[$attribute['code']])) {
                        continue;
                    }
                    if ($this->getAdditionalAttributeValue($item, $attribute['id']) != $data[$attribute['code']]) {
                        $attributes++;
                        break;
                    }
                }
                if (!$attributes) {
                    $found = true;
                    break;
                }
            }
        }

        return $found;
    }

    /**
     * Get assigned product collection
     *
     * @param  int $productId
     * @return object
     */
    public function getAssignProductCollection($productId)
    {
        return $this->_itemsCollection->create()
            ->addFieldToFilter("product_id", $productId);
    }

    /**
     * Assign Product to Seller
     *
     * @param  array $data
     * @param  int   $flag [optional]
     * @return array
     * @throws \Exception
     */
    public function assignProduct($data, $flag = 0)
    {
        $result = [
            'assign_id' => 0,
            'product_id' => 0,
            'error' => 0,
            'msg' => '',
            'qty' => 0,
            'flag' => 0,
            'status' => 1,
            'type' => 0
        ];
        $productId = (int) $data['product_id'];
        $condition = (int) $data['product_condition'];
        $qty = (int) $data['quantity_and_stock_status'];
        $image = $data['image'];
        $product = $this->getProduct($productId);
        $result['condition'] = $condition;
        if ($qty < 0) {
            $qty = 0;
        }
        $assignProductData = [
            'product_id' => $productId,
            'owner_id' => $this->getSellerIdByProductId($productId),
            'seller_id' => $this->getCustomerId(),
            'qty' => $qty,
            'price' => (float) $data['price'],
            'description' => $data['description'],
            'condition' => $condition,
            'type' => $product->getTypeId(),
            'created_at' => date('Y-m-d'),
            'image' => $image,
            'status' => 1
        ];
        if ($image == '') {
            unset($assignProductData['image']);
        }
        if ($data['del'] == 1) {
            $assignProductData['image'] = "";
        }
        $model = $this->_items->create();

        if ($flag == 1) {
            $assignId = $data['assign_id'];
            $assignData = $this->getAssignDataByAssignId($assignId);
            if (!$assignData->getId()) {
                return $result;
            }
            $oldImage = $assignData->getImage();
            if ($oldImage != $image && $image != "") {
                $assignProductData['image'] = $image;
            }
            $result['old_qty'] = $assignData->getQty();
            $result['prev_status'] = $assignData->getStatus();
            $result['flag'] = 1;
            unset($assignProductData['created_at']);
            if ($this->isEditApprovalRequired()) {
                $result['status'] = 0;
                $assignProductData['status'] = 0;
            }
            $model->addData($assignProductData)->setId($assignId)->save();
        } else {
            if ($this->isAddApprovalRequired()) {
                $result['status'] = 0;
                $assignProductData['status'] = 0;
            }
            $model->setData($assignProductData)->save();
        }
        $this->saveAdditionalAttributes($model, $product, $data);
        if ($model->getId()) {
            $result['product_id'] = $productId;
            $result['qty'] = $qty;
            $result['assign_id'] = $model->getId();
        }

        return $result;
    }

    /**
     * Get additional attribute value
     *
     * @param  object $assigned
     * @param  int    $attributeId
     * @return string|null
     */
    public function getAdditionalAttributeValue($assigned, $attributeId)
    {
        if (!$assigned) {
            return '';
        }
        $value = '';
        $storeId = $this->_storeManager->getStore()->getStoreId();
        $dataCollection = $this->_dataCollection->create()
            ->addFieldToFilter("type", $attributeId)
            ->addFieldToFilter("assign_id", $assigned->getId())
            ->addFieldToFilter("store_view", $storeId)
            ->setPageSize(1)
            ->getFirstItem();
        if ($dataCollection->getValue()) {
            $value = $dataCollection->getValue();
        }
        return $value;
    }

    /**
     * Get additional attribute value raw
     *
     * @param  obj   $assigned
     * @param  array $attribute
     * @return string|null $attribute
     */
    public function getAdditionalAttributeValueRaw($assigned, $attribute)
    {
        if (!$assigned) {
            return '';
        }
        $value = $this->getAdditionalAttributeValue($assigned, $attribute['id']);
        if ($attribute['input_type'] == 'select') {
            foreach ($attribute['options'] as $option) {
                if ($value == $option['value']) {
                    $value = $option['label'];
                    break;
                }
            }
        }
        return $value;
    }

    /**
     * Get allowed attributes
     *
     * @param  \Magento\Catalog\Model\Product $product
     * @return array
     */
    public function getAllowedAttributes($product)
    {
        $allowedAttributes = [];

        if (!$product instanceof \Magento\Catalog\Model\Product) {
             $product = $this->productRepository->getById($product->getId());
        }
        $attributes = $product->getTypeInstance(true)->getSetAttributes($product);

        /**
         * @var \Magento\Eav\Model\Entity\Attribute $attribute
         */
        foreach ($attributes as $attribute) {
            $attrCode = $attribute->getAttributeCode();
            try {
                $backendAttribute = $attribute->getBackend()->getAttribute();
                if ($backendAttribute->getAllowSellersToSet() && !in_array($attrCode, $this->skipAttributes)) {
                    $frontendInput = $attribute->getFrontendInput();
                    $allowedAttributes[$attrCode]['input_type'] = $frontendInput;
                    $allowedAttributes[$attrCode]['is_required'] = $attribute->getIsRequired();
                    $allowedAttributes[$attrCode]['id'] = $attribute->getId();
                    $allowedAttributes[$attrCode]['code'] = $attrCode;
                    $allowedAttributes[$attrCode]['title'] = $attribute->getFrontendLabel();
                    $allowedAttributes[$attrCode]['label'] = __($attribute->getFrontendLabel());
                    switch ($frontendInput) {
                        case 'text':
                            break;
                        case 'select':
                            $attributeOptions = $attribute->getSource()->getAllOptions();
                            $allowedAttributes[$attrCode]['options'] = $attributeOptions;
                            break;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return $allowedAttributes;
    }

    /**
     * Save additional attributes
     *
     * @param  object                         $model
     * @param  \Magento\Catalog\Model\Product $product
     * @param  array                          $dataInput
     * @return void
     */
    public function saveAdditionalAttributes($model, $product, $dataInput)
    {
        $storeId = $this->_storeManager->getStore()->getStoreId();
        $attributes = $product->getTypeInstance(true)->getSetAttributes($product);
        /**
         * @var \Magento\Eav\Model\Entity\Attribute $attribute
         */
        foreach ($attributes as $attribute) {
            $attrCode = $attribute->getAttributeCode();
            try {
                $backendAttribute = $attribute->getBackend()->getAttribute();
                if ($backendAttribute->getAllowSellersToSet()) {
                    $value = '';
                    if (isset($dataInput[$attrCode])) {
                        $value = $dataInput[$attrCode];
                    }
                    $dataCollection = $this->_dataCollection->create()
                        ->addFieldToFilter("type", $attribute->getId())
                        ->addFieldToFilter("assign_id", $model->getId())
                        ->addFieldToFilter("store_view", $storeId);
                    $dataTable = $dataCollection->getMainTable();
                    if ($dataCollection->getSize()) {
                        $columnData = ['value' => $value];
                        $where = [
                            'type = ?' => $attribute->getId(),
                            'assign_id = ?' => $model->getId(),
                            'store_view = ?' => $storeId
                        ];
                        $this->updateData($dataTable, $columnData, $where);
                    } else {
                        $data = [];
                        $data['type'] = $attribute->getId();
                        $data['assign_id'] = $model->getId();
                        $data['value'] = $value;
                        $data['is_default'] = 0;
                        $data['status'] = 1;
                        $data['store_view'] = $storeId;
                        $this->insertData($dataTable, $data);
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }
    }

    /**
     * db connection
     *
     * @return ResourceConnection
     */
    public function getConnection()
    {
        $connection = $this->resourceConnection->getConnection(ResourceConnection::DEFAULT_CONNECTION);
        return $connection;
    }

    /**
     * Update records
     *
     * @param string $tableName
     * @param array  $columnData
     * @param array  $where
     */
    public function updateData($tableName, $columnData, $where)
    {
        $connection = $this->getConnection();
        try {
            $connection->beginTransaction();
            $connection->update($tableName, $columnData, $where);
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
        }
    }

    /**
     * Insert records
     *
     * @param string $tableName
     * @param array  $columnData
     */
    public function insertData($tableName, $columnData)
    {
        $connection = $this->getConnection();
        try {
            $connection->beginTransaction();
            $connection->insert($tableName, $columnData);
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
        }
    }

    /**
     * Upload images
     *
     * @param  int $numberOfImages
     * @param  int $assignId
     * @return void
     */
    public function uploadImages($numberOfImages, $assignId)
    {
        if ($numberOfImages > 0) {
            $uploadPath = $this->_filesystem
                ->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA)
                ->getAbsolutePath('marketplace/assignproduct/product/');
            $uploadPath .= $assignId;
            $fileId = "showcase";
            for ($i = 1; $i <= $numberOfImages; $i++) {
                $this->uploadImage($fileId, $uploadPath, $assignId, $i);
            }
        }
    }

    /**
     * Get description
     *
     * @param  int $assignId
     * @return string|null
     */
    public function getDescription($assignId)
    {
        $storeId = $this->getStore()->getId();
        $desc = '';
        $collection = $this->_data->create()->getCollection()
            ->addFieldToFilter('assign_id', $assignId)
            ->addFieldToFilter('is_default', 1)
            ->addFieldToFilter('type', 2)
            ->addFieldToFilter('store_view', $storeId)
            ->addFieldToFilter('value', ['neq' => ''])
            ->setPageSize(1);
        if ($collection->getSize()) {
            $item = $collection->getFirstItem();
            $desc = $item->getValue();
        } else {
            $collection = $this->_data->create()->getCollection()
                ->addFieldToFilter('assign_id', $assignId)
                ->addFieldToFilter('is_default', 1)
                ->addFieldToFilter('type', 2)
                ->addFieldToFilter('value', ['neq' => ''])
                ->setPageSize(1);
            if ($collection->getSize()) {
                $item = $collection->getFirstItem();
                $desc = $item->getValue();
            }
        }
        if (!$desc) {
            $desc = $this->_items->create()->load($assignId)->getDescription();
        }
        return $desc;
    }

    /**
     * Check product
     *
     * @param  int $isAdd
     * @return array
     */
    public function checkProduct($isAdd = 0)
    {
        $result = ['msg' => '', 'error' => 0];
        $assignId = (int) $this->_request->getParam('id');
        if ($assignId == 0) {
            $result['error'] = 1;
            $result['msg'] = 'Invalid request.';
            return $result;
        }
        if ($isAdd == 1) {
            $productId = $assignId;
        } else {
            $assignData = $this->getAssignDataByAssignId($assignId);
            $productId = $assignData->getProductId();
        }
        $product = $this->getProduct($productId);
        if (!$product->getId()) {
            $result['error'] = 1;
            $result['msg'] = 'Product does not exist.';
            return $result;
        }
        $productType = $product->getTypeId();
        $allowedProductTypes = $this->getAllowedProductTypes();
        if (!in_array($productType, $allowedProductTypes)) {
            $result['error'] = 1;
            $result['msg'] = 'Product type not allowed.';
            return $result;
        }
        $sellerId = $this->getSellerIdByProductId($productId);

        $customerId = $this->getCustomerId();
        if ($sellerId == $customerId) {
            $result['error'] = 1;
            $result['msg'] = 'Product is your own product.';
            return $result;
        }
        return $result;
    }
}
