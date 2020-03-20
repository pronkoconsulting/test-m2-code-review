<?php
namespace Vendor\Module\Helper;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Option;
use Magento\Catalog\Model\ProductRepository;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Checkout\Model\Cart;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\Session;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Registry;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Quote\Model\Quote\Item\OptionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Vendor\AppointedAttributes\Helper\Validation;
use Vendor\Marketplace\Model\ProductFactory;
use Vendor\MpAssignProduct\Model\AssociatesFactory;
use Vendor\MpAssignProduct\Model\DataFactory;
use Vendor\MpAssignProduct\Model\ItemsFactory;

/**
 * Class Data
 */
class Data extends \Vendor\MpAssignProduct\Helper\Data
{
    /**
     * @var Validation
     */
    protected $_validationHelper;

    /**
     * Attributes to ignore
     *
     * @var array
     */
    protected $skipAttributes = ['price', 'quantity_and_stock_status'];
    
    /**
     * @var ProductRepository 
     */
    protected $_productRepository;

    /**
     * Data construct
     *
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param ManagerInterface $messageManager
     * @param Session $customerSession
     * @param CustomerFactory $customer
     * @param Filesystem $filesystem
     * @param FormKey $formKey
     * @param \Magento\Framework\Pricing\Helper\Data $currency
     * @param ResourceConnection $resource
     * @param UploaderFactory $fileUploaderFactory
     * @param \Magento\Catalog\Model\ProductFactory $productFactory
     * @param Cart $cart
     * @param ProductFactory $mpProductFactory
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
     * @param ProductRepository $productRepository
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        ManagerInterface $messageManager,
        Session $customerSession,
        CustomerFactory $customer,
        Filesystem $filesystem,
        FormKey $formKey,
        \Magento\Framework\Pricing\Helper\Data $currency,
        ResourceConnection $resource,
        UploaderFactory $fileUploaderFactory,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        Cart $cart,
        ProductFactory $mpProductFactory,
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
        ProductRepository $productRepository
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
        $this->_validationHelper = $validation;
        $this->_productRepository=$productRepository;
    }

    /**
     * function definition
     *
     * @param $data
     * @param $type
     * @return array
     */
    public function validateData($data, $type)
    {
        if ($type == "configurable") {
            return $this->validateConfigData($data);
        }

        $rules = $this->_validationHelper->getAttributeRules();
        $result = [];
        $isSuccess = true;
        $requiredFields = $this->_validationHelper->getRequiredFields();
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
                        $rule .= $rule
                            ? ucfirst($rulePart)
                            : $rulePart;
                    }
                    if (is_callable([$this->_validationHelper, $rule])) {
                        $validationStatus = $this->_validationHelper->$rule($value);
                        $result[$field][$ruleCode] = $validationStatus;
                        if (!$validationStatus) {
                            $isSuccess = false;
                        }
                    }
                }
            }
        }

        if ($this->isDuplicateProduct($data)) {
            $result['error'] = true;
            $result['msg'] = 'You Already have same product with same attributes.';
            $isSuccess = false;
        }

        $result['error'] = !$isSuccess;
        return $result;
    }

    /**
     * function definition
     *
     * @param $data
     * @return int
     */
    public function isDuplicateProduct($data)
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
        $found = 0;
        $allowedAttributes = $this->getAllowedAttributes($this->getProduct($productId));
        if (count($assigned)) {
            foreach ($assigned as $item) {
                reset($allowedAttributes);
                $attributes = 0;
                foreach ($allowedAttributes as $attribute) {
                    if ($this->getAdditionalAttributeValue($item, $attribute['id']) != $data[$attribute['code']]) {
                        $attributes++;
                        break;
                    }
                }
                if (!$attributes) {
                    $found = 1;
                    break;
                }
            }
        }
        return $found;
    }

    /**
     * function definition
     *
     * @param $product
     * @return array
     */
    public function getAllowedAttributes($productId)
    {
        $allowedAttributes = [];
        $product=$this->_productRepository->getById($productId);
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
            } catch (Exception $e) {
                continue;
            }
        }

        return $allowedAttributes;
    }

    /**
     * function definition
     *
     * @param $assigned
     * @param $attributeId
     * @return bool
     */
    public function getAdditionalAttributeValue($assigned, $attributeId)
    {
        if (!$assigned) {
            return false;
        }
        $value = false;
        $storeId = $this->_storeManager->getStore()->getStoreId();
        $oldBase = $this->_dataCollection->create()
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
     * function definition
     *
     * @param $productId
     * @return mixed
     */
    public function getAssignProductCollection($productId)
    {
        $collection = $this->_itemsCollection->create();
        $collection->addFieldToFilter("product_id", $productId);
        return $collection;
    }

    /**
     * Assign Product to Seller
     *
     * @param array $data
     * @param int $flag [optional]
     * @return array
     * @throws Exception
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
        if ($qty < 0) {
            $qty = 0;
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
            'status' => 1,
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
            if ($assignData->getId() > 0) {
                $oldImage = $assignData->getImage();
                if ($oldImage != $image && $image != "") {
                    $assignProductData['image'] = $image;
                }
                $oldQty = $assignData->getQty();
                $status = $assignData->getStatus();
                $result['old_qty'] = $oldQty;
                $result['prev_status'] = $status;
                $result['flag'] = 1;
                unset($assignProductData['created_at']);
                if ($this->isEditApprovalRequired()) {
                    $result['status'] = 0;
                    $assignProductData['status'] = 0;
                }
            } else {
                return $result;
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
        if ($model->getId() > 0) {
            $result['product_id'] = $productId;
            $result['qty'] = $qty;
            $result['assign_id'] = $model->getId();
        }

        return $result;
    }

    /**
     * function definition
     *
     * @param $model
     * @param $product
     * @param $dataInput
     */
    public function saveAdditionalAttributes($model, $product, $dataInput)
    {
        $storeId = $this->_storeManager->getStore()->getStoreId();
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
                    $old_base = $this->_dataCollection->create()
                        ->addFieldToFilter("type", $attribute->getId())
                        ->addFieldToFilter("assign_id", $model->getId())
                        ->addFieldToFilter("store_view", $storeId);
                    if ($old_base->getSize()) {
                        foreach ($old_base as $key) {
                            $key->setValue($value)->save();
                        }
                    } else {
                        $data = [];
                        $data['type'] = $attribute->getId();
                        $data['assign_id'] = $model->getId();
                        $data['value'] = $value;
                        $data['is_default'] = 0;
                        $data['status'] = 1;
                        $data['store_view'] = $storeId;
                        $this->_data->create()->setData($data)->save();
                    }
                }
            } catch (Exception $e) {
                continue;
            }
        }
    }

    /**
     * function definition
     *
     * @param $assigned
     * @param $attribute
     * @return string
     */
    public function getAdditionalAttributeValueRaw($assigned, $attribute)
    {
        if (!$assigned) {
            return '';
        }
        $value = '';
        $store_id = $this->_storeManager->getStore()->getStoreId();
        $old_base = $this->_dataCollection->create()
            ->addFieldToFilter("type", $attribute['id'])
            ->addFieldToFilter("assign_id", $assigned->getId())
            ->addFieldToFilter("store_view", $store_id);
        if ($old_base->getSize()) {
            foreach ($old_base as $key) {
                $value = $key->getValue();
            }
        }
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
     * function definition
     *
     * @param $numberOfImages
     * @param $assignId
     */
    public function uploadImages($numberOfImages, $assignId)
    {
        if ($numberOfImages > 0) {
            $uploadPath = $this->_filesystem
                ->getDirectoryRead(DirectoryList::MEDIA)
                ->getAbsolutePath('marketplace/assignproduct/product/');
            $uploadPath .= $assignId;
            $count = 0;
            for ($i = 0; $i < $numberOfImages; $i++) {
                $count++;
                $fileId = "showcase";
                $this->uploadImage($fileId, $uploadPath, $assignId, $count);
            }
        }
    }

    /**
     * function definition
     *
     * @param $assignId
     * @return mixed
     */
    public function getDescription($assignId)
    {
        return $this->_items->create()->load($assignId)->getDescription();
    }

    /**
     * function definition
     *
     * @param $assignId
     * @param int $isAdd
     * @return array
     */
    public function checkProduct($assignId, $isAdd = 0)
    {
        $result = ['msg' => '', 'error' => 0];
        if (!$assignId) {
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
        if (!$product) {
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
