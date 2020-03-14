<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace VendorName\ModuleName\Helper;

use Vendor\MpAssignProduct\Helper\Data as MpAssignProductData;
use Vendor\AppointedAttributes\Helper\Validation;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Customer\Model\Session;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Pricing\Helper\Data as PricingData;
use Magento\Framework\App\ResourceConnection;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Catalog\Model\ProductFactory;
use Magento\Checkout\Model\Cart;
use Vendor\Marketplace\Model\ProductFactory as MarketplaceProductFactory;
use Vendor\MpAssignProduct\Model\{ItemsFactory, DataFactory, AssociatesFactory};
use Magento\Quote\Model\Quote\Item\OptionFactory;
use Magento\Framework\Registry;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Catalog\Model\Product\Option;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\App\Filesystem\DirectoryList;

/**
 * Class Data
 *
 * @package VendorName\ModuleName\Helper
 */
class Data extends MpAssignProductData
{
    const STATUS_ENABLE = 1;
    const IS_DEFAULT = 0;
    const IS_DEFAULT_FOR_COLLECTION = 1;
    const TYPE_PRODUCT = 2;
    const FOUND_ENABLE = 1;
    const FOUND_DISABLE = 0;
    const DEFAULT_ASSIGN_PRODUCT_ID = 0;
    const WITHOUT_ERROR = 0;
    const WITH_ERROR = 1;
    const IS_ADD_NO = 0;
    const IS_ADD_YES = 1;
    const PRODUCT_ID_ZERO = 0;
    const ZERO = 0;
    const ONE = 1;

    /**
     * @var Validation
     */
    protected $validationHelper;

    /**
     * @var array
     */
    protected $skipAttributes = ['price', 'quantity_and_stock_status'];

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * Data constructor.
     *
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param ManagerInterface $messageManager
     * @param Session $customerSession
     * @param CustomerFactory $customer
     * @param Filesystem $filesystem
     * @param FormKey $formKey
     * @param PricingData $currency
     * @param ResourceConnection $resource
     * @param UploaderFactory $fileUploaderFactory
     * @param ProductFactory $productFactory
     * @param Cart $cart
     * @param MarketplaceProductFactory $mpProductFactory
     * @param ItemsFactory $itemsFactory
     * @param DataFactory $dataFactory
     * @param AssociatesFactory $associatesFactory
     * @param OptionFactory $quoteOption
     * @param CollectionFactory $mpProductCollectionFactory
     * @param SellerCollection $sellerCollectionFactory
     * @param ItemsCollection $itemsCollectionFactory
     * @param QuoteCollection $quoteCollectionFactory
     * @param DataCollection $dataCollectionFactory
     * @param ProductCollection $productCollectionFactory
     * @param Registry $coreRegistry
     * @param StockRegistryInterface $stockRegistry
     * @param TransportBuilder $transportBuilder
     * @param StateInterface $inlineTranslation
     * @param PriceCurrencyInterface $priceCurrency
     * @param File $fileDriver
     * @param ConfigurableCollection $configurableCollection
     * @param Option $customOptions
     * @param Validation $validation
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
        PricingData $currency,
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
        File $fileDriver,
        ConfigurableCollection $configurableCollection,
        Option $customOptions,
        Validation $validation,
        ProductRepositoryInterface $productRepository
    )
    {
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
        $this->productRepository = $productRepository;
    }

    /**
     * Validate Data
     *
     * @param $data
     * @param $type
     * @return array
     * @throws NoSuchEntityException
     */
    public function validateData($data, $type)
    {
        if ($type == "configurable") {
            return $this->validateConfigData($data);
        }
        $result = [];
        $isSuccess = true;
        $this->validateRules($data, $isSuccess, $result);
        $this->validateAssigned($data, $isSuccess, $result);
        $result['error'] = !$isSuccess;
        return $result;
    }

    /**
     * Validate rules part
     *
     * @param $data
     * @param $isSuccess
     * @param $result
     * @return mixed
     */
    protected function validateRules($data, &$isSuccess, &$result)
    {
        $rules = $this->validationHelper->getAttributeRules();

        $requiredFields = $this->validationHelper->getRequiredFields();
        $compareResult = array_diff_key($requiredFields, $data);

        if (count($compareResult)) {
            $result['error'] = true;
            reset($compareResult);
            $result['msg'] = ucfirst(key($compareResult)) . ' is required field';
            return $result;
        }

        foreach ($data as $field => $value) {
            if (isset($rules[$field])) {
                foreach ($rules[$field] as $ruleCode => $ruleStatus) {
                    $ruleParts = explode('-', $ruleCode);
                    $rule = '';
                    foreach ($ruleParts as $rulePart) {
                        $rule .= $rule ? ucfirst($rulePart) : $rulePart;
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
    }

    /**
     * Validate assigned
     *
     * @param $data
     * @param $isSuccess
     * @param $result
     * @throws NoSuchEntityException
     */
    protected function validateAssigned($data, &$isSuccess, &$result)
    {
        $productId = $data['product_id'];
        $assignId = self::DEFAULT_ASSIGN_PRODUCT_ID;
        if (isset($data['assign_id'])) {
            $assignId = $data['assign_id'];
        }

        $assigned = $this->getCollection()
            ->addFieldToFilter('product_id', ['eq' => $productId])
            ->addFieldToFilter('entity_id', ['neq' => $assignId])
            ->addFieldToFilter('seller_id', ['eq' => $this->getCustomerId()]);
        $found = self::FOUND_DISABLE;
        $allowedAttributes = $this->getAllowedAttributes($this->getProduct($productId));
        if ($assigned->getSize()) {
            foreach ($assigned as $item) {
                reset($allowedAttributes);
                $attributes = self::ZERO;
                foreach ($allowedAttributes as $attribute) {
                    if ($this->getAdditionalAttributeValue($item, $attribute['id']) != $data[$attribute['code']]) {
                        $attributes++;
                        break;
                    }
                }
                if (!$attributes) {
                    $found = self::FOUND_ENABLE;
                    break;
                }
            }
        }
        if ($found) {
            $result['error'] = true;
            $result['msg'] = __('You Already have same product with same attributes.');
            $isSuccess = false;
        }
    }

    /**
     * Get assign product collection
     *
     * @param $productId
     * @return mixed
     */
    public function getAssignProductCollection($productId)
    {
        return $this->itemsCollectionFactory->create()->addFieldToFilter("product_id", $productId);
    }

    /**
     * Assign Product to seller
     *
     * @param $data
     * @param int $flag
     * @return array
     */
    public function assignProduct($data, $flag = self::ZERO)
    {
        $result = [
            'assign_id' => self::ZERO,
            'product_id' => self::ZERO,
            'error' => self::ZERO,
            'msg' => '',
            'qty' => self::ZERO,
            'flag' => self::ZERO,
            'status' => self::ONE,
            'type' => self::ZERO
        ];
        $productId = (int)$data['product_id'];
        $condition = (int)$data['product_condition'];
        $qty = (int)$data['quantity_and_stock_status'];
        $price = (float)$data['price'];
        $description = $data['description'];
        $image = $data['image'];
        $ownerId = $this->getSellerIdByProductId($productId);
        $sellerId = $this->getCustomerId();
        $product = $this->getProduct($productId);
        $type = $product->getTypeId();
        $date = date('Y-m-d');
        $result['condition'] = $condition;
        if ($qty < self::ZERO) {
            $qty = self::ZERO;
        }
        $assignProductData = [
            'product_id' => $productId,
            'owner_id' => $ownerId,
            'seller_id' => $sellerId,
            'qty' => $qty,
            'price' => $price,
            'description' => $description,
            'condition' => $condition,
            'type' => $type,
            'created_at' => $date,
            'image' => $image,
            'status' => self::ONE,
        ];
        if ($image == '') {
            unset($assignProductData['image']);
        }
        if ($data['del'] == self::ONE) {
            $assignProductData['image'] = "";
        }
        $model = $this->itemsCollectionFactory->create();

        if ($flag == self::ONE) {
            $assignId = $data['assign_id'];
            $assignData = $this->getAssignDataByAssignId($assignId);
            if ($assignData->getId() > self::ZERO) {
                $oldImage = $assignData->getImage();
                if ($oldImage != $image && $image != "") {
                    $assignProductData['image'] = $image;
                }
                $oldQty = $assignData->getQty();
                $status = $assignData->getStatus();
                $result['old_qty'] = $oldQty;
                $result['prev_status'] = $status;
                $result['flag'] = self::ONE;
                unset($assignProductData['created_at']);
                if ($this->isEditApprovalRequired()) {
                    $result['status'] = self::ZERO;
                    $assignProductData['status'] = self::ZERO;
                }
            } else {
                return $result;
            }
            $model->addData($assignProductData)->setId($assignId)->save();
        } else {
            if ($this->isAddApprovalRequired()) {
                $result['status'] = self::ZERO;
                $assignProductData['status'] = self::ZERO;
            }
            $model->setData($assignProductData)->save();
        }
        $this->saveAdditionalAttributes($model, $product, $data);
        if ($model->getId() > self::ZERO) {
            $result['product_id'] = $productId;
            $result['qty'] = $qty;
            $result['assign_id'] = $model->getId();
        }

        return $result;
    }

    /**
     * Get additional attribute value
     *
     * @param $assigned
     * @param $attributeId
     * @return string
     */
    public function getAdditionalAttributeValue($assigned, $attributeId)
    {
        if (!$assigned) {
            return '';
        }
        $value = '';
        $storeId = $this->storeManager->getStore()->getStoreId();
        $oldBase = $this->dataCollectionFactory->create()
            ->addFieldToFilter("type", $attributeId)
            ->addFieldToFilter("assign_id", $assigned->getId())
            ->addFieldToFilter("store_view", $storeId);
        if ($oldBase->getSize()) {
            foreach ($oldBase as $key) {
                $value = $key->getValue();
            }
        }

        return $value;
    }

    /**
     * Get additional attribute value raw
     *
     * @param $assigned
     * @param $attribute
     * @return string
     */
    public function getAdditionalAttributeValueRaw($assigned, $attribute)
    {
        $value = '';
        $additionalAttributeValue = $this->getAdditionalAttributeValue($assigned, $attribute);

        if ($attribute['input_type'] == 'select') {
            foreach ($attribute['options'] as $option) {
                if ($additionalAttributeValue == $option['value']) {
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
     * @param $product
     * @return array
     * @throws NoSuchEntityException
     */
    public function getAllowedAttributes($product)
    {
        $allowedAttributes = [];
        //@todo Need to debug this part, why we load product again if we already have product in argument of function
        $product = $this->productRepository->getById($product->getId());
        $attributes = $product->getTypeInstance(true)->getSetAttributes($product);
        /** @var Attribute $attribute */
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
     * @param $model
     * @param $product
     * @param $dataInput
     */
    public function saveAdditionalAttributes($model, $product, $dataInput)
    {
        $storeId = $this->storeManager->getStore()->getStoreId();
        $attributes = $product->getTypeInstance(true)->getSetAttributes($product);
        /** @var Attribute $attribute */
        foreach ($attributes as $attribute) {
            $attrCode = $attribute->getAttributeCode();
            try {
                $backendAttribute = $attribute->getBackend()->getAttribute();
                if ($backendAttribute->getAllowSellersToSet()) {
                    $value = '';
                    if (isset($dataInput[$attrCode])) {
                        $value = $dataInput[$attrCode];
                    }
                    $oldBase = $this->dataCollectionFactory->create()
                        ->addFieldToFilter("type", $attribute->getId())
                        ->addFieldToFilter("assign_id", $model->getId())
                        ->addFieldToFilter("store_view", $storeId);
                    if ($oldBase->getSize()) {
                        foreach ($oldBase as $key) {
                            $key->setValue($value)->save();
                        }
                    } else {
                        $data = [];
                        $data['type'] = $attribute->getId();
                        $data['assign_id'] = $model->getId();
                        $data['value'] = $value;
                        $data['is_default'] = self::IS_DEFAULT;
                        $data['status'] = self::STATUS_ENABLE;
                        $data['store_view'] = $storeId;
                        $this->dataCollectionFactory->create()->setData($data)->save();
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }
    }

    /**
     * Upload images
     *
     * @param $numberOfImages
     * @param $assignId
     */
    public function uploadImages($numberOfImages, $assignId)
    {
        if ($numberOfImages > self::ZERO) {
            $uploadPath = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath('marketplace/assignproduct/product/');
            $uploadPath .= $assignId;
            $count = self::ZERO;
            for ($i = self::ZERO; $i < $numberOfImages; $i++) {
                $count++;
                $fileId = "showcase";
                $this->uploadImage($fileId, $uploadPath, $assignId, $count);
            }
        }
    }

    /**
     * Get description
     *
     * @param $assignId
     * @return string
     */
    public function getDescription($assignId)
    {
        $store_id = $this->getStore()->getId();
        $desc = '';
        $collection = $this->getAssignedCollection($assignId)->addFieldToFilter('store_view', $store_id);
        if ($collection->getSize()) {
            foreach ($collection as $key) {
                $desc = $key->getValue();
                break;
            }
        } else {
            $collection = $this->getAssignedCollection($assignId);
            foreach ($collection as $key) {
                $desc = $key->getValue();
                break;
            }
        }
        if (!$desc) {
            $desc = $this->itemsCollectionFactory->create()->load($assignId)->getDescription();
        }

        return $desc;
    }

    /**
     * Check product
     *
     * @param int $isAdd
     * @return array
     */
    public function checkProduct($isAdd = self::IS_ADD_NO)
    {
        $result = ['msg' => '', 'error' => self::WITHOUT_ERROR];
        $assignId = (int)$this->request->getParam('id');
        if ($assignId == self::DEFAULT_ASSIGN_PRODUCT_ID) {
            $result['error'] = self::WITH_ERROR;
            $result['msg'] = __('Invalid request.');
            return $result;
        }
        if ($isAdd == self::IS_ADD_YES) {
            $productId = $assignId;
        } else {
            $assignData = $this->getAssignDataByAssignId($assignId);
            $productId = $assignData->getProductId();
        }
        $product = $this->getProduct($productId);
        if ($product->getId() <= self::PRODUCT_ID_ZERO) {
            $result['error'] = self::WITH_ERROR;
            $result['msg'] = __('Product does not exist.');
            return $result;
        }
        $productType = $product->getTypeId();
        $allowedProductTypes = $this->getAllowedProductTypes();
        if (!in_array($productType, $allowedProductTypes)) {
            $result['error'] = self::WITH_ERROR;
            $result['msg'] = __('Product type not allowed.');
            return $result;
        }
        $sellerId = $this->getSellerIdByProductId($productId);

        $customerId = $this->getCustomerId();
        if ($sellerId == $customerId) {
            $result['error'] = self::WITH_ERROR;
            $result['msg'] = __('Product is your own product.');
            return $result;
        }
        return $result;
    }

    /**
     * @param $assignId
     * @return mixed
     */
    protected function getAssignedCollection($assignId)
    {
        return $this->dataCollectionFactory->create()->getCollection()
            ->addFieldToFilter('assign_id', $assignId)
            ->addFieldToFilter('is_default', self::IS_DEFAULT_FOR_COLLECTION)
            ->addFieldToFilter('type', self::TYPE_PRODUCT);
    }
}