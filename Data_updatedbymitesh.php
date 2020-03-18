<?php
namespace Vendor\AppointedAttributes\Helper;

use \Magento\Framework\Mail\Template\TransportBuilder;
use \Magento\Framework\Translate\Inline\StateInterface;
use \Magento\Framework\App\RequestInterface;

class Data extends \Vendor\MpAssignProduct\Helper\Data
{
    /**
     * @var \Vendor\AppointedAttributes\Helper\Validation
     */
    protected $validationHelper;

    protected $skipAttributes = ['price', 'quantity_and_stock_status'];

    protected $_request;
    /**
     * __construct
     *
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Framework\Data\Form\FormKey $formKey
     * @param \Magento\Framework\Pricing\Helper\Data $currency
     * @param \Magento\Framework\App\ResourceConnection $resource
     * @param \Magento\MediaStorage\Model\File\UploaderFactory $fileUploaderFactory
     * @param \Magento\Catalog\Model\ProductFactory $productFactory
     * @param \Magento\Checkout\Model\Cart $cart
     * @param \Vendor\Marketplace\Model\ProductFactory $mpProductFactory
     * @param \Vendor\MpAssignProduct\Model\ItemsFactory $itemsFactory
     * @param \Vendor\MpAssignProduct\Model\AssociatesFactory $associatesFactory
     * @param CollectionFactory $mpProductCollectionFactory
     * @param SellerCollection $sellerCollectionFactory
     * @param ItemsCollection $itemsCollectionFactory
     * @param QuoteCollection $quoteCollectionFactory
     * @param ProductCollection $productCollectionFactory
     * @param \Magento\Framework\Registry $coreRegistry
     * @param \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry
     * @param Validation $validation
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Customer\Model\SessionFactory $customerSession,
        \Magento\Customer\Model\CustomerFactory $customer,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Framework\Data\Form\FormKey $formKey,
        \Magento\Framework\Pricing\Helper\Data $currency,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\MediaStorage\Model\File\UploaderFactory $fileUploaderFactory,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Checkout\Model\Cart $cart,
        \Vendor\Marketplace\Model\ProductFactory $mpProductFactory,
        \Vendor\MpAssignProduct\Model\ItemsFactory $itemsFactory,
        \Vendor\MpAssignProduct\Model\DataFactory $dataFactory,
        \Vendor\MpAssignProduct\Model\AssociatesFactory $associatesFactory,
        \Magento\Quote\Model\Quote\Item\OptionFactory $quoteOption,
        \Vendor\MpAssignProduct\Model\CollectionFactory $mpProductCollectionFactory,
        SellerCollection $sellerCollectionFactory,
        ItemsCollection $itemsCollectionFactory,
        \Magento\Quote\Model\ResouceModel\Quote\CollectionFactory $quoteCollectionFactory,
        DataCollection $dataCollectionFactory,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\Framework\Registry $coreRegistry,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        \Magento\Framework\Filesystem\Driver\File $fileDriver,
        ConfigurableCollection $configurableCollection,
        \Magento\Catalog\Model\Product\Option $customOptions,
        Validation $validation,
        RequestInterface $request
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
        $this->_request = $request;
    }

    /**
     * Validate Data
     *
     * @param array $data
     * @return array
     */
    public function validateData($data, $type)
    {
        if ($type == "configurable") {
            return $this->validateConfigData($data);
        }

        $rules = $this->validationHelper->getAttributeRules();
        $result = [];
        $isSuccess = true;
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
                        $rule .= $rule
                            ? ucfirst($rulePart)
                            : $rulePart
                        ;
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
        if ($found) {
            $result['error'] = true;
            $result['msg'] =  'You Already have same product with same attributes.';
            $isSuccess = false;
        }


        $result['error'] = !$isSuccess;
        return $result;
    }

    public function getAssignProductCollection($productId)
    {
        $collection = $this->_itemsCollection->create();
        /*
        $joinTable = $this->_resource->getTableName('marketplace_datafeedback');
        $sql = 'mp.seller_id = main_table.seller_id';
        $sql .= ' and mp.status = 1';
        $fields = [];
        $fields[] = 'status';
        $fields[] = 'seller_id as mp_seller_id';
        $fields[] = "sum(mp.feed_price+mp.feed_value+mp.feed_quality) as total_rating";
        $fields[] = "count(mp.seller_id) as count";
        $collection->getSelect()->joinLeft($joinTable.' as mp', $sql, $fields);
        $field = 'sum(mp.feed_price+mp.feed_value+mp.feed_quality)/(count(mp.seller_id)*3)';
        $collection->getSelect()->columns(['rating' => new \Zend_Db_Expr($field)]);
        */

        $joinTable = $this->_resource->getTableName('marketplace_userdata');
        $sql = 'mpud.seller_id = main_table.seller_id';
        $fields = [];
        $fields[] = 'shop_url';
        $fields[] = 'shop_title';
        $fields[] = 'logo_pic';
        $fields[] = 'is_seller';
        //$collection->getSelect()->joinLeft($joinTable.' as mpud', $sql, $fields);
        //$collection->getSelect()->group('main_table.seller_id')->where('mpud.is_seller = 1');
        $collection->addFieldToFilter("product_id", $productId);
        return $collection;
    }

    /**
     * Assign Product to Seller
     *
     * @param array $data
     * @param int $flag [optional]
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
        $price = (float) $data['price'];
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
            $oldPrice = $assignData->getPrice();
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

    public function getAdditionalAttributeValue($assigned, $attributeId) {
        if (!$assigned) {
            return '';
        }
        $value = '';
        $storeId = $this->_storeManager->getStore()->getStoreId();
        $oldBase = $this->_dataCollection->create()
            ->addFieldToFilter("type", $attributeId)
            ->addFieldToFilter("assign_id", $assigned->getId())
            ->addFieldToFilter("store_view", $storeId);
        if ($oldBase->getSize()) {
            foreach($oldBase as $key) {
                $value = $key->getValue();
            }
        }
        return $value;
    }

    public function getAdditionalAttributeValueRaw($assigned, $attribute) {
        if (!$assigned) {
            return '';
        }
        $value = '';
        $storeId = $this->_storeManager->getStore()->getStoreId();
        $oldBase = $this->_dataCollection->create()
            ->addFieldToFilter("type", $attribute['id'])
            ->addFieldToFilter("assign_id", $assigned->getId())
            ->addFieldToFilter("store_view", $storeId);
        if ($oldBase->getSize()) {
            foreach($oldBase as $key) {
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

    public function getAllowedAttributes($product)
    {
        $allowedAttributes = [];
        /** @var \Magento\Catalog\Model\Product $product */
        //$product = $product->load($product->getId());
        $attributes = $product->getTypeInstance(true)->getSetAttributes($product);
        /** @var \Magento\Eav\Model\Entity\Attribute $attribute */
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

    public function saveAdditionalAttributes($model, $product, $dataInput) {
        $storeId = $this->_storeManager->getStore()->getStoreId();
        $attributes = $product->getTypeInstance(true)->getSetAttributes($product);
        /** @var \Magento\Eav\Model\Entity\Attribute $attribute */
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
                        foreach($old_base as $key) {
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
            } catch (\Exception $e) {
                continue;
            }
        }
    }
    public function uploadImages($numberOfImages, $assignId)
    {
        if ($numberOfImages > 0) {
            $uploadPath = $this->_filesystem
                ->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA)
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
    public function getDescription($assignId) {
        $store_id = $this->getStore()->getId();
        $desc = '';
        $collection = $this->_data->create()->getCollection()
            ->addFieldToFilter('assign_id', $assignId)
            ->addFieldToFilter('is_default', 1)
            ->addFieldToFilter('type', 2)
            ->addFieldToFilter('store_view', $store_id);
        if ($collection->getSize()) {
            foreach ($collection as $key) {
                $desc = $key->getValue();
            }
        } else {
            $collection = $this->_data->create()->getCollection()
                ->addFieldToFilter('assign_id', $assignId)
                ->addFieldToFilter('is_default', 1)
                ->addFieldToFilter('type', 2);
            foreach ($collection as $key) {
                $desc = $key->getValue(); break;
            }
        }
        if (!$desc) {
            $desc = $this->_items->create()->load($assignId)->getDescription();
        }
        return $desc;
    }

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
