<?php
namespace Glew\Service\Model\Types;
class Products {
    public $products = array();
    protected $helper;
    protected $productFactory;
    protected $objectManager;
    private $pageNum;
    protected $resource;
    private $productAttributes = array();
    /**
     * @param \Glew\Service\Helper\Data $helper
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productFactory
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \Magento\Framework\App\ResourceConnection $resource
     */
    public function __construct(
        \Glew\Service\Helper\Data $helper,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productFactory,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\App\ResourceConnection $resource
    ) {
        $this->helper = $helper;
        $this->productFactory = $productFactory;
        $this->objectManager = $objectManager;
        $this->resource = $resource;
    }
    public function load($pageSize, $pageNum, $startDate = null, $endDate = null, $sortDir, $filterBy, $id, $customAttr)
    {
        $config = $this->helper->getConfig();
        $this->pageNum = $pageNum;
        $this->_getProductAttribtues();
        if( $id ) {
            $collection = $this->productFactory->create()
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('entity_id', $id)
                ->setFlag('has_stock_status_filter', true);
        } elseif ($startDate && $endDate) {
            $from = date('Y-m-d 00:00:00', strtotime($startDate));
            $to = date('Y-m-d 23:59:59', strtotime($endDate));
            $collection = $this->productFactory->create()
                ->addAttributeToSelect('*')
                ->addAttributeToFilter($filterBy, array('from' => $from, 'to' => $to));
        } else {
            $collection = $this->productFactory->create()
                ->addAttributeToSelect('*');
        }

        $collection->setStore($this->helper->getStore());
        $collection->setOrder('entity_id', 'asc');
        $collection->setCurPage($pageNum);
        $collection->setPageSize($pageSize);
        if ($collection->getLastPageNumber() < $pageNum) {
            return $this;
        }
        $connection = $this->resource->getConnection();
        $catalogProductEntityTableName = $this->resource->getTableName('catalog_product_entity');
        $catalogProductEntityVarcharTableName = $this->resource->getTableName('catalog_product_entity_varchar');
        foreach ($collection as $product) {
            $productId = $product->getId();
            $model = $this->objectManager->create('\Glew\Service\Model\Types\Product')->parse($productId, $this->productAttributes);
            if ($model) {
                if ($customAttr) {
                    $sql = "SELECT color.value FROM " . $catalogProductEntityVarcharTableName . " AS color WHERE color.attribute_id = 260 AND color.row_id = " . $productId;
                    $model->sku_color = $connection->fetchOne($sql);
                }
                $model->cross_sell_products = $this->_getCrossSellProducts($product);
                $model->up_sell_products = $this->_getUpSellProducts($product);
                $model->related_products = $this->_getRelatedProducts($product);
                $this->products[] = $model;
            }
        }
        return $this->products;
    }
    protected function _getProductAttribtues()
    {
        if (!$this->productAttributes) {
            $attributes = $this->objectManager->get('\Magento\Eav\Model\ResourceModel\Entity\Attribute\Collection')
                ->addFieldToFilter(\Magento\Eav\Model\Entity\Attribute\Set::KEY_ENTITY_TYPE_ID, 4)
                ->load()
                ->getItems();
            foreach ($attributes as $attribute) {
                if (!$attribute) {
                    continue;
                }
                $this->productAttributes[$attribute->getData('attribute_code')] = $attribute->usesSource();
            }
        }
    }
    protected function _getCrossSellProducts($product)
    {
        $productArray = array();
        $collection = $product->getCrossSellProductCollection();
        if ($collection) {
            foreach ($collection as $item) {
                $productArray[] = $item->getId();
            }
        }
        return $productArray;
    }
    protected function _getUpSellProducts($product)
    {
        $productArray = array();
        $collection = $product->getUpSellProductCollection();
        if ($collection) {
            foreach ($collection as $item) {
                $productArray[] = $item->getId();
            }
        }
        return $productArray;
    }
    protected function _getRelatedProducts($product)
    {
        $productArray = array();
        $collection = $product->getRelatedProductCollection();
        if ($collection) {
            foreach ($collection as $item) {
                $productArray[] = $item->getId();
            }
        }
        return $productArray;
    }
}
