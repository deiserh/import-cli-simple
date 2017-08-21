<?php

/**
 * TechDivision\Import\Cli\UrlRewrite\AddUpdateOperationTest
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 5
 *
 * @author    Tim Wagner <t.wagner@techdivision.com>
 * @copyright 2016 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/techdivision/import-product
 * @link      http://www.techdivision.com
 */

namespace TechDivision\Import\Cli\UrlRewrite;

use TechDivision\Import\Utils\OperationKeys;
use TechDivision\Import\Cli\AbstractIntegrationTest;
use TechDivision\Import\Utils\EntityStatus;
use TechDivision\Import\Product\Utils\ColumnKeys;
use TechDivision\Import\Product\Utils\MemberNames;
use TechDivision\Import\Product\Observers\UrlRewriteObserver;

/**
 * Test class for the product URL rewrite functionality.
 *
 * @author    Tim Wagner <t.wagner@techdivision.com>
 * @copyright 2016 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/techdivision/import-product
 * @link      http://www.techdivision.com
 */
class AddUpdateOperationTest extends AbstractIntegrationTest
{

    /**
     * The operation that has to be tested.
     *
     * @return string The operation name
     */
    protected function getOperationName()
    {
        return OperationKeys::ADD_UPDATE;
    }

    /**
     * The configuration used for the test case.
     *
     * @return string The absolute path to the configuration file
     * @see \TechDivision\Import\Cli\AbstractIntegrationTest::getConfigurationFile()
     */
    protected function getConfigurationFile()
    {
        return sprintf('%s/ce/2.1.x/conf/url-rewrite/add-update-operation/techdivision-import.json', $this->getFilesDir());
    }

    /**
     * The additional DI configuration used for the test case.
     *
     * @return string The absolute path to the DI configuration file
     * @see \TechDivision\Import\Cli\AbstractIntegrationTest::getDiConfigurationFile()
     */
    protected function getDiConfigurationFile()
    {
        return sprintf('%s/ce/2.1.x/conf/services.xml', $this->getFilesDir());
    }

    /**
     * Test's the add-update operation with a simple product without category relation.
     *
     * @return void
     */
    public function testAddUpdateWithSimpleProduct()
    {

        // prepare the file we want to import
        $filename = $this->prepareFileWithSingleRow();

        // process the import operation
        $this->processImport();

        // make sure, the flag file for a successfull import exists
        $this->assertFileExists(sprintf('%s.imported', $filename));

        // initialize the product bunch processor instance
        $productBunchProcessor = $this->getProductBunchProcessor();

        // try to load the imported product by its SKU
        $product = $productBunchProcessor->loadProduct($sku = '24-MB01');

        // assert the expected product entity data
        $this->assertArrayHasKey('sku', $product);
        $this->assertSame($sku, $product[MemberNames::SKU]);
        $this->assertSame(4, (integer) $product[MemberNames::ATTRIBUTE_SET_ID]);
        $this->assertSame('simple', $product[MemberNames::TYPE_ID]);
        $this->assertSame(0, (integer) $product[MemberNames::HAS_OPTIONS]);
        $this->assertSame(0, (integer) $product[MemberNames::REQUIRED_OPTIONS]);
        $this->assertSame('2016-10-24 12:36:00', $product[MemberNames::CREATED_AT]);
        $this->assertSame('2016-10-24 12:36:00', $product[MemberNames::UPDATED_AT]);

        // try to load the URL rewrites by their SKU and count them
        $urlRewrites = $productBunchProcessor->getUrlRewritesBySku($sku);
        $this->assertCount(1, $urlRewrites);

        // try to load the URL rewrite product category relations and count them
        $urlRewriteProductCategories = $productBunchProcessor->getUrlRewriteProductCategoriesBySku($sku);
        $this->assertCount(1, $urlRewriteProductCategories);

        // load the first and only found URL rewrite
        $urlRewrite = reset($urlRewrites);

        // assert the expected size and values
        $this->assertSame($product[MemberNames::ENTITY_ID], $urlRewrite[MemberNames::ENTITY_ID]);
        $this->assertSame('product', $urlRewrite[MemberNames::ENTITY_TYPE]);
        $this->assertSame('joust-duffle-bag-s', $urlRewrite[MemberNames::REQUEST_PATH]);
        $this->assertSame(sprintf('catalog/product/view/id/%d', $product[MemberNames::ENTITY_ID]), $urlRewrite[MemberNames::TARGET_PATH]);
        $this->assertSame(0, (integer) $urlRewrite[MemberNames::REDIRECT_TYPE]);
        $this->assertSame(1, (integer) $urlRewrite[MemberNames::STORE_ID]);
        $this->assertSame(1, (integer) $urlRewrite[MemberNames::IS_AUTOGENERATED]);
        $this->assertSame(serialize(array()), $urlRewrite[MemberNames::METADATA]);
        $this->assertNull($urlRewrite[MemberNames::DESCRIPTION]);

        // load the first and only found URL rewrite product category relation
        $urlRewriteProductCategory = reset($urlRewriteProductCategories);

        // assert the expected size and values
        $this->assertSame((integer) $urlRewrite[MemberNames::URL_REWRITE_ID], (integer) $urlRewriteProductCategory[MemberNames::URL_REWRITE_ID]);
        $this->assertSame((integer) $product[MemberNames::ENTITY_ID], (integer) $urlRewriteProductCategory[MemberNames::PRODUCT_ID]);
        $this->assertSame(2, (integer) $urlRewriteProductCategory[MemberNames::CATEGORY_ID]);
    }

    /**
     * Test's the add-update operation with a simple product without category relation
     * when URL key has changed with a second import.
     *
     * @return void
     */
    public function testAddUpdateWithSimpleProductAndChangedUrlKey()
    {

        // initialize the array for for the names of the import files
        $filenames = array(
            $this->prepareFileWithSingleRow(),
            $this->prepareFileWithSingleRow(array(MemberNames::URL_KEY => 'joust-duffle-bag-s-new'))
        );

        // invoke the import operation twice to import both files
        $this->processImport(sizeof($filenames));

        // make sure, the flag file for a successfull import exists
        foreach ($filenames as $filename) {
            $this->assertFileExists(sprintf('%s.imported', $filename));
        }

        // initialize the product bunch processor instance
        $productBunchProcessor = $this->getProductBunchProcessor();

        // try to load the imported product by its SKU
        $product = $productBunchProcessor->loadProduct($sku = '24-MB01');

        // try to load the URL rewrites by their SKU and count them
        $urlRewrites = $productBunchProcessor->getUrlRewritesBySku($sku );
        $this->assertCount(2, $urlRewrites);

        // try to load the URL rewrite product category relations by their SKU and count them
        $urlRewriteProductCategories = $productBunchProcessor->getUrlRewriteProductCategoriesBySku($sku);
        $this->assertCount(2, $urlRewriteProductCategories);

        // load the first found URL rewrite
        $urlRewrite = array_shift($urlRewrites);

        // assert the values
        $this->assertSame($product[MemberNames::ENTITY_ID], $urlRewrite[MemberNames::ENTITY_ID]);
        $this->assertSame('product', $urlRewrite[MemberNames::ENTITY_TYPE]);
        $this->assertSame('joust-duffle-bag-s.html', $urlRewrite[MemberNames::REQUEST_PATH]);
        $this->assertSame('joust-duffle-bag-s-new.html', $urlRewrite[MemberNames::TARGET_PATH]);
        $this->assertSame(301, (integer) $urlRewrite[MemberNames::REDIRECT_TYPE]);
        $this->assertSame(1, (integer) $urlRewrite[MemberNames::STORE_ID]);
        $this->assertSame(1, (integer) $urlRewrite[MemberNames::IS_AUTOGENERATED]);
        $this->assertSame(serialize(array()), $urlRewrite[MemberNames::METADATA]);
        $this->assertNull($urlRewrite[MemberNames::DESCRIPTION]);

        // load the first found URL rewrite product category relation
        $urlRewriteProductCategory = array_shift($urlRewriteProductCategories);

        // assert the expected size and values
        $this->assertSame((integer) $urlRewrite[MemberNames::URL_REWRITE_ID], (integer) $urlRewriteProductCategory[MemberNames::URL_REWRITE_ID]);
        $this->assertSame((integer) $product[MemberNames::ENTITY_ID], (integer) $urlRewriteProductCategory[MemberNames::PRODUCT_ID]);
        $this->assertSame(2, (integer) $urlRewriteProductCategory[MemberNames::CATEGORY_ID]);

        // load the second and last found URL rewrite
        $urlRewrite = array_shift($urlRewrites);

        // assert the values
        $this->assertSame($product[MemberNames::ENTITY_ID], $urlRewrite[MemberNames::ENTITY_ID]);
        $this->assertSame('product', $urlRewrite[MemberNames::ENTITY_TYPE]);
        $this->assertSame('joust-duffle-bag-s-new.html', $urlRewrite[MemberNames::REQUEST_PATH]);
        $this->assertSame(sprintf('catalog/product/view/id/%d', $product[MemberNames::ENTITY_ID]), $urlRewrite[MemberNames::TARGET_PATH]);
        $this->assertSame(0, (integer) $urlRewrite[MemberNames::REDIRECT_TYPE]);
        $this->assertSame(1, (integer) $urlRewrite[MemberNames::STORE_ID]);
        $this->assertSame(1, (integer) $urlRewrite[MemberNames::IS_AUTOGENERATED]);
        $this->assertSame(serialize(array()), $urlRewrite[MemberNames::METADATA]);

        // load the second and last found URL rewrite product category relation
        $urlRewriteProductCategory = array_shift($urlRewriteProductCategories);

        // assert the expected size and values
        $this->assertSame((integer) $urlRewrite[MemberNames::URL_REWRITE_ID], (integer) $urlRewriteProductCategory[MemberNames::URL_REWRITE_ID]);
        $this->assertSame((integer) $product[MemberNames::ENTITY_ID], (integer) $urlRewriteProductCategory[MemberNames::PRODUCT_ID]);
        $this->assertSame(2, (integer) $urlRewriteProductCategory[MemberNames::CATEGORY_ID]);
    }

    /**
     * Test's the add-update operation with a simple product whithout category relation when
     * URL key has changed and again changed back to the orginal value with a third import.
     *
     * @return void
     */
    public function testAddUpdateWithSimpleProductAndChangedUrlKeyBackToOriginal()
    {

        // initialize the array for for the names of the import files
        $filenames = array(
            $this->prepareFileWithSingleRow(),
            $this->prepareFileWithSingleRow(array(MemberNames::URL_KEY => 'joust-duffle-bag-s-new')),
            $this->prepareFileWithSingleRow(array(MemberNames::URL_KEY => 'joust-duffle-bag-s'))
        );

        // invoke the import operation twice to import both files
        $this->processImport(sizeof($filenames));

        // make sure, the flag file for a successfull import exists
        foreach ($filenames as $filename) {
            $this->assertFileExists(sprintf('%s.imported', $filename));
        }

        // initialize the product bunch processor instance
        $productBunchProcessor = $this->getProductBunchProcessor();

        // try to load the imported product by its SKU
        $product = $productBunchProcessor->loadProduct($sku = '24-MB01');

        // try to load the URL rewrites by their SKU and count them
        $urlRewrites = $productBunchProcessor->getUrlRewritesBySku($sku );
        $this->assertCount(2, $urlRewrites);

        // try to load the URL rewrite product category relations by their SKU and count them
        $urlRewriteProductCategories = $productBunchProcessor->getUrlRewriteProductCategoriesBySku($sku);
        $this->assertCount(2, $urlRewriteProductCategories);

        // load the first found URL rewrite
        $urlRewrite = array_shift($urlRewrites);

        // assert the values
        $this->assertSame($product[MemberNames::ENTITY_ID], $urlRewrite[MemberNames::ENTITY_ID]);
        $this->assertSame('product', $urlRewrite[MemberNames::ENTITY_TYPE]);
        $this->assertSame('joust-duffle-bag-s.html', $urlRewrite[MemberNames::REQUEST_PATH]);
        $this->assertSame(sprintf('catalog/product/view/id/%d', $product[MemberNames::ENTITY_ID]), $urlRewrite[MemberNames::TARGET_PATH]);
        $this->assertSame(0, (integer) $urlRewrite[MemberNames::REDIRECT_TYPE]);
        $this->assertSame(1, (integer) $urlRewrite[MemberNames::STORE_ID]);
        $this->assertSame(1, (integer) $urlRewrite[MemberNames::IS_AUTOGENERATED]);
        $this->assertSame(serialize(array()), $urlRewrite[MemberNames::METADATA]);
        $this->assertNull($urlRewrite[MemberNames::DESCRIPTION]);

        // load the first found URL rewrite product category relation
        $urlRewriteProductCategory = array_shift($urlRewriteProductCategories);

        // assert the expected size and values
        $this->assertSame((integer) $urlRewrite[MemberNames::URL_REWRITE_ID], (integer) $urlRewriteProductCategory[MemberNames::URL_REWRITE_ID]);
        $this->assertSame((integer) $product[MemberNames::ENTITY_ID], (integer) $urlRewriteProductCategory[MemberNames::PRODUCT_ID]);
        $this->assertSame(2, (integer) $urlRewriteProductCategory[MemberNames::CATEGORY_ID]);

        // load the second and last found URL rewrite
        $urlRewrite = array_shift($urlRewrites);

        // assert the values
        $this->assertSame($product[MemberNames::ENTITY_ID], $urlRewrite[MemberNames::ENTITY_ID]);
        $this->assertSame('product', $urlRewrite[MemberNames::ENTITY_TYPE]);
        $this->assertSame('joust-duffle-bag-s-new.html', $urlRewrite[MemberNames::REQUEST_PATH]);
        $this->assertSame('joust-duffle-bag-s.html', $urlRewrite[MemberNames::TARGET_PATH]);
        $this->assertSame(301, (integer) $urlRewrite[MemberNames::REDIRECT_TYPE]);
        $this->assertSame(1, (integer) $urlRewrite[MemberNames::STORE_ID]);
        $this->assertSame(1, (integer) $urlRewrite[MemberNames::IS_AUTOGENERATED]);
        $this->assertSame(serialize(array()), $urlRewrite[MemberNames::METADATA]);
        $this->assertNull($urlRewrite[MemberNames::DESCRIPTION]);

        // load the second and last found URL rewrite product category relation
        $urlRewriteProductCategory = array_shift($urlRewriteProductCategories);

        // assert the expected size and values
        $this->assertSame((integer) $urlRewrite[MemberNames::URL_REWRITE_ID], (integer) $urlRewriteProductCategory[MemberNames::URL_REWRITE_ID]);
        $this->assertSame((integer) $product[MemberNames::ENTITY_ID], (integer) $urlRewriteProductCategory[MemberNames::PRODUCT_ID]);
        $this->assertSame(2, (integer) $urlRewriteProductCategory[MemberNames::CATEGORY_ID]);
    }

    /**
     * Test's the add-update operation with a simple product without category relation
     * and one additional language.
     *
     * @return void
     */
    public function testAddUpdateWithSimpleProductAndAdditionalLanguage()
    {

        // create an addtional store view
        $storeId = $this->getImportProcessor()->persistStore(
            array(
                MemberNames::CODE         => $storeViewCode = 'default_second',
                MemberNames::WEBSITE_ID   => 1,
                MemberNames::GROUP_ID     => 1,
                MemberNames::NAME         => 'Second Default Store View',
                MemberNames::SORT_ORDER   => 1,
                MemberNames::IS_ACTIVE    => 1,
                EntityStatus::MEMBER_NAME => EntityStatus::STATUS_CREATE
            )
        );

        // prepare the file we want to import
        $filename = $this->prepareFile(
            array(
                $this->prepareRow(),
                $this->prepareRow(
                    array(
                        ColumnKeys::STORE_VIEW_CODE => $storeViewCode,
                        ColumnKeys::URL_KEY         => $urlKey = 'joust-duffle-bag-s-de.html'
                    )
                )
            )
        );

        // process the import operation
        $this->processImport();

        // make sure, the flag file for a successfull import exists
        $this->assertFileExists(sprintf('%s.imported', $filename));

        // initialize the product bunch processor instance
        $productBunchProcessor = $this->getProductBunchProcessor();

        // try to load the imported product by its SKU
        $product = $productBunchProcessor->loadProduct($sku = '24-MB01');

        // try to load the URL rewrites by their SKU and count them
        $urlRewrites = $productBunchProcessor->getUrlRewritesBySku($sku );
        $this->assertCount(2, $urlRewrites);

        // try to load the URL rewrite product category relations by their SKU and count them
        $urlRewriteProductCategories = $productBunchProcessor->getUrlRewriteProductCategoriesBySku($sku);
        $this->assertCount(2, $urlRewriteProductCategories);

        // load the first found URL rewrite
        $urlRewrite = array_shift($urlRewrites);

        // assert the values
        $this->assertSame($product[MemberNames::ENTITY_ID], $urlRewrite[MemberNames::ENTITY_ID]);
        $this->assertSame('product', $urlRewrite[MemberNames::ENTITY_TYPE]);
        $this->assertSame('joust-duffle-bag-s.html', $urlRewrite[MemberNames::REQUEST_PATH]);
        $this->assertSame(sprintf('catalog/product/view/id/%d', $product[MemberNames::ENTITY_ID]), $urlRewrite[MemberNames::TARGET_PATH]);
        $this->assertSame(0, (integer) $urlRewrite[MemberNames::REDIRECT_TYPE]);
        $this->assertSame(1, (integer) $urlRewrite[MemberNames::STORE_ID]);
        $this->assertSame(1, (integer) $urlRewrite[MemberNames::IS_AUTOGENERATED]);
        $this->assertSame(serialize(array()), $urlRewrite[MemberNames::METADATA]);
        $this->assertNull($urlRewrite[MemberNames::DESCRIPTION]);

        // load the first found URL rewrite product category relation
        $urlRewriteProductCategory = array_shift($urlRewriteProductCategories);

        // assert the expected size and values
        $this->assertSame((integer) $urlRewrite[MemberNames::URL_REWRITE_ID], (integer) $urlRewriteProductCategory[MemberNames::URL_REWRITE_ID]);
        $this->assertSame((integer) $product[MemberNames::ENTITY_ID], (integer) $urlRewriteProductCategory[MemberNames::PRODUCT_ID]);
        $this->assertSame(2, (integer) $urlRewriteProductCategory[MemberNames::CATEGORY_ID]);

        // load the second and last found URL rewrite
        $urlRewrite = array_shift($urlRewrites);

        // assert the values
        $this->assertSame($product[MemberNames::ENTITY_ID], $urlRewrite[MemberNames::ENTITY_ID]);
        $this->assertSame('product', $urlRewrite[MemberNames::ENTITY_TYPE]);
        $this->assertSame($urlKey, $urlRewrite[MemberNames::REQUEST_PATH]);
        $this->assertSame(sprintf('catalog/product/view/id/%d', $product[MemberNames::ENTITY_ID]), $urlRewrite[MemberNames::TARGET_PATH]);
        $this->assertSame(0, (integer) $urlRewrite[MemberNames::REDIRECT_TYPE]);
        $this->assertSame($storeId, (integer) $urlRewrite[MemberNames::STORE_ID]);
        $this->assertSame(1, (integer) $urlRewrite[MemberNames::IS_AUTOGENERATED]);
        $this->assertSame(serialize(array()), $urlRewrite[MemberNames::METADATA]);
        $this->assertNull($urlRewrite[MemberNames::DESCRIPTION]);

        // load the second and last found URL rewrite product category relation
        $urlRewriteProductCategory = array_shift($urlRewriteProductCategories);

        // assert the expected size and values
        $this->assertSame((integer) $urlRewrite[MemberNames::URL_REWRITE_ID], (integer) $urlRewriteProductCategory[MemberNames::URL_REWRITE_ID]);
        $this->assertSame((integer) $product[MemberNames::ENTITY_ID], (integer) $urlRewriteProductCategory[MemberNames::PRODUCT_ID]);
        $this->assertSame(2, (integer) $urlRewriteProductCategory[MemberNames::CATEGORY_ID]);
    }

    /**
     * Test's the add-update operation with a simple product with one category relation.
     *
     * @return void
     */
    public function testAddUpdateWithSimpleProductAndCategoryRelation()
    {

        // create a new category
        $categoryId = $this->createCategory('Testcategory');

        // prepare the file we want to import
        $filename = $this->prepareFileWithSingleRow(array(ColumnKeys::CATEGORIES => 'Default Category/Testcategory'));

        // process the import operation
        $this->processImport();

        // make sure, the flag file for a successfull import exists
        $this->assertFileExists(sprintf('%s.imported', $filename));

        // initialize the product bunch processor instance
        $productBunchProcessor = $this->getProductBunchProcessor();

        // try to load the imported product by its SKU
        $product = $productBunchProcessor->loadProduct($sku = '24-MB01');

        // try to load the URL rewrites by their SKU and count them
        $urlRewrites = $productBunchProcessor->getUrlRewritesBySku($sku );
        $this->assertCount(2, $urlRewrites);

        // try to load the URL rewrite product category relations by their SKU and count them
        $urlRewriteProductCategories = $productBunchProcessor->getUrlRewriteProductCategoriesBySku($sku);
        $this->assertCount(2, $urlRewriteProductCategories);

        // load the first found URL rewrite
        $urlRewrite = array_shift($urlRewrites);

        // assert the values
        $this->assertSame($product[MemberNames::ENTITY_ID], $urlRewrite[MemberNames::ENTITY_ID]);
        $this->assertSame('product', $urlRewrite[MemberNames::ENTITY_TYPE]);
        $this->assertSame('joust-duffle-bag-s.html', $urlRewrite[MemberNames::REQUEST_PATH]);
        $this->assertSame(sprintf('catalog/product/view/id/%d', $product[MemberNames::ENTITY_ID]), $urlRewrite[MemberNames::TARGET_PATH]);
        $this->assertSame(0, (integer) $urlRewrite[MemberNames::REDIRECT_TYPE]);
        $this->assertSame(1, (integer) $urlRewrite[MemberNames::STORE_ID]);
        $this->assertSame(1, (integer) $urlRewrite[MemberNames::IS_AUTOGENERATED]);
        $this->assertSame(serialize(array()), $urlRewrite[MemberNames::METADATA]);
        $this->assertNull($urlRewrite[MemberNames::DESCRIPTION]);

        // load the first found URL rewrite product category relation
        $urlRewriteProductCategory = array_shift($urlRewriteProductCategories);

        // assert the expected size and values
        $this->assertSame((integer) $urlRewrite[MemberNames::URL_REWRITE_ID], (integer) $urlRewriteProductCategory[MemberNames::URL_REWRITE_ID]);
        $this->assertSame((integer) $product[MemberNames::ENTITY_ID], (integer) $urlRewriteProductCategory[MemberNames::PRODUCT_ID]);
        $this->assertSame(2, (integer) $urlRewriteProductCategory[MemberNames::CATEGORY_ID]);

        // load the second and last found URL rewrite
        $urlRewrite = array_shift($urlRewrites);

        // assert the values
        $this->assertSame($product[MemberNames::ENTITY_ID], $urlRewrite[MemberNames::ENTITY_ID]);
        $this->assertSame('product', $urlRewrite[MemberNames::ENTITY_TYPE]);
        $this->assertSame('testcategory/joust-duffle-bag-s.html', $urlRewrite[MemberNames::REQUEST_PATH]);
        $this->assertSame(sprintf('catalog/product/view/id/%d/category/%d', $product[MemberNames::ENTITY_ID], $categoryId));
        $this->assertSame(0, (integer) $urlRewrite[MemberNames::REDIRECT_TYPE]);
        $this->assertSame(1, (integer) $urlRewrite[MemberNames::STORE_ID]);
        $this->assertSame(1, (integer) $urlRewrite[MemberNames::IS_AUTOGENERATED]);
        $this->assertSame(serialize(array(UrlRewriteObserver::CATEGORY_ID => $categoryId)), $urlRewrite[MemberNames::METADATA]);
        $this->assertNull($urlRewrite[MemberNames::DESCRIPTION]);

        // load the second and last found URL rewrite product category relation
        $urlRewriteProductCategory = array_shift($urlRewriteProductCategories);

        // assert the expected size and values
        $this->assertSame((integer) $urlRewrite[MemberNames::URL_REWRITE_ID], (integer) $urlRewriteProductCategory[MemberNames::URL_REWRITE_ID]);
        $this->assertSame((integer) $product[MemberNames::ENTITY_ID], (integer) $urlRewriteProductCategory[MemberNames::PRODUCT_ID]);
        $this->assertSame(2, (integer) $urlRewriteProductCategory[MemberNames::CATEGORY_ID]);
    }

    /**
     * Test's the add-update operation with a simple product without category relation
     * and one additional language.
     *
     * @return void
     */
    public function testAddUpdateWithSimpleProductAndCategoryRelationAndTwoWebsiteAndTwoStoresEach()
    {

        // create a new category
        $categoryId = $this->createCategory('Testcategory');

        // prepare the file we want to import
        $filename = $this->prepareFileWithSingleRow(array(ColumnKeys::CATEGORIES => 'Default Category/Testcategory'));

        // create an addtional store view for the default group/website
        $this->getImportProcessor()->persistStore(
            array(
                MemberNames::CODE         => 'default_second',
                MemberNames::WEBSITE_ID   => 1,
                MemberNames::GROUP_ID     => 1,
                MemberNames::NAME         => 'Second Default Store View',
                MemberNames::SORT_ORDER   => 1,
                MemberNames::IS_ACTIVE    => 1,
                EntityStatus::MEMBER_NAME => EntityStatus::STATUS_CREATE
            )
        );

        // create a new store website
        $websiteId = $this->getImportProcessor()->persistStoreWebsite(
            array(
                MemberNames::CODE             => 'ch',
                MemberNames::NAME             => 'Swizerland Website',
                MemberNames::SORT_ORDER       => 1,
                MemberNames::DEFAULT_GROUP_ID => 0,
                MemberNames::IS_DEFAULT       => 0,
                EntityStatus::MEMBER_NAME     => EntityStatus::STATUS_CREATE
            )
        );

        // create a new store group
        $groupId = $this->getImportProcessor()->persistStoreGroup(
            array(
                MemberNames::WEBSITE_ID       => $websiteId,
                MemberNames::NAME             => 'Swizerland Store Group',
                MemberNames::ROOT_CATEGORY_ID => 2,
                MemberNames::DEFAULT_STORE_ID => 0,
                EntityStatus::MEMBER_NAME     => EntityStatus::STATUS_CREATE
            )
        );

        // create the first store view
        $this->getImportProcessor()->persistStore(
            array(
                MemberNames::CODE         => 'ch_FR',
                MemberNames::WEBSITE_ID   => $websiteId,
                MemberNames::GROUP_ID     => $groupId,
                MemberNames::NAME         => 'Switzerland French Store',
                MemberNames::SORT_ORDER   => 0,
                MemberNames::IS_ACTIVE    => 1,
                EntityStatus::MEMBER_NAME => EntityStatus::STATUS_CREATE
            )
        );

        // create the second store view
        $storeId = $this->getImportProcessor()->persistStore(
            array(
                MemberNames::CODE         => 'ch_DE',
                MemberNames::WEBSITE_ID   => $websiteId,
                MemberNames::GROUP_ID     => $groupId,
                MemberNames::NAME         => 'Switzerland German Store',
                MemberNames::SORT_ORDER   => 1,
                MemberNames::IS_ACTIVE    => 1,
                EntityStatus::MEMBER_NAME => EntityStatus::STATUS_CREATE
            )
        );

        // update the store group with the default store ID
        $this->getImportProcessor()->persistStoreGroup(
            array(
                MemberNames::GROUP_ID         => $groupId,
                MemberNames::WEBSITE_ID       => $websiteId,
                MemberNames::NAME             => 'Swizerland Store Group',
                MemberNames::ROOT_CATEGORY_ID => 2,
                MemberNames::DEFAULT_STORE_ID => $storeId,
                EntityStatus::MEMBER_NAME     => EntityStatus::STATUS_UPDATE
            )
        );

        // update the store website with the default group ID
        $websiteId = $this->getImportProcessor()->persistStoreWebsite(
            array(
                MemberNames::WEBSITE_ID       => $websiteId,
                MemberNames::CODE             => 'ch',
                MemberNames::NAME             => 'Swizerland Website',
                MemberNames::SORT_ORDER       => 0,
                MemberNames::DEFAULT_GROUP_ID => $groupId,
                MemberNames::IS_DEFAULT       => 0,
                EntityStatus::MEMBER_NAME     => EntityStatus::STATUS_UPDATE
            )
        );

        // prepare the file we want to import
        $filename = $this->prepareFileWithSingleRow(
            array(
                ColumnKeys::CATEGORIES       => 'Default Category/Testcategory',
                ColumnKeys::PRODUCT_WEBSITES => 'base,ch'
            )
        );

        // process the import operation
        $this->processImport();

        // make sure, the flag file for a successfull import exists
        $this->assertFileExists(sprintf('%s.imported', $filename));

        // initialize the product bunch processor instance
        $productBunchProcessor = $this->getProductBunchProcessor();

        // try to load the imported product by its SKU
        $product = $productBunchProcessor->loadProduct($sku = '24-MB01');

        // try to load the URL rewrites by their SKU and count them
        $urlRewrites = $productBunchProcessor->getUrlRewritesBySku($sku);
        $this->assertCount($expectedCount = 8, $urlRewrites);

        // try to load the URL rewrite product category relations by their SKU and count them
        $urlRewriteProductCategories = $productBunchProcessor->getUrlRewriteProductCategoriesBySku($sku);
        $this->assertCount($expectedCount, $urlRewriteProductCategories);

        for ($i = 1; $i <= $expectedCount; $i++) {
            // load the seventh found URL rewrite
            $urlRewrite = array_shift($urlRewrites);

            // assert the values
            $this->assertSame($product[MemberNames::ENTITY_ID], $urlRewrite[MemberNames::ENTITY_ID]);
            $this->assertSame('product', $urlRewrite[MemberNames::ENTITY_TYPE]);
            $this->assertSame('joust-duffle-bag-s.html', $urlRewrite[MemberNames::REQUEST_PATH]);
            $this->assertSame(sprintf('catalog/product/view/id/%d', $product[MemberNames::ENTITY_ID]), $urlRewrite[MemberNames::TARGET_PATH]);
            $this->assertSame(0, (integer) $urlRewrite[MemberNames::REDIRECT_TYPE]);
            $this->assertSame($i, (integer) $urlRewrite[MemberNames::STORE_ID]);
            $this->assertSame(1, (integer) $urlRewrite[MemberNames::IS_AUTOGENERATED]);
            $this->assertSame(serialize(array()), $urlRewrite[MemberNames::METADATA]);
            $this->assertNull($urlRewrite[MemberNames::DESCRIPTION]);

            // load the seventh found URL rewrite product category relation
            $urlRewriteProductCategory = array_shift($urlRewriteProductCategories);

            // assert the expected size and values
            $this->assertSame((integer) $urlRewrite[MemberNames::URL_REWRITE_ID], (integer) $urlRewriteProductCategory[MemberNames::URL_REWRITE_ID]);
            $this->assertSame((integer) $product[MemberNames::ENTITY_ID], (integer) $urlRewriteProductCategory[MemberNames::PRODUCT_ID]);
            $this->assertSame(2, (integer) $urlRewriteProductCategory[MemberNames::CATEGORY_ID]);

            // load the eight found URL rewrite
            $urlRewrite = array_shift($urlRewrites);

            // assert the values
            $this->assertSame($product[MemberNames::ENTITY_ID], $urlRewrite[MemberNames::ENTITY_ID]);
            $this->assertSame('product', $urlRewrite[MemberNames::ENTITY_TYPE]);
            $this->assertSame('testcategory/joust-duffle-bag-s.html', $urlRewrite[MemberNames::REQUEST_PATH]);
            $this->assertSame(sprintf('catalog/product/view/id/%d/category/%d', $product[MemberNames::ENTITY_ID], $categoryId));
            $this->assertSame(0, (integer) $urlRewrite[MemberNames::REDIRECT_TYPE]);
            $this->assertSame($i, (integer) $urlRewrite[MemberNames::STORE_ID]);
            $this->assertSame(1, (integer) $urlRewrite[MemberNames::IS_AUTOGENERATED]);
            $this->assertSame(serialize(array(UrlRewriteObserver::CATEGORY_ID => $categoryId)), $urlRewrite[MemberNames::METADATA]);
            $this->assertNull($urlRewrite[MemberNames::DESCRIPTION]);

            // load the eight found URL rewrite product category relation
            $urlRewriteProductCategory = array_shift($urlRewriteProductCategories);

            // assert the expected size and values
            $this->assertSame((integer) $urlRewrite[MemberNames::URL_REWRITE_ID], (integer) $urlRewriteProductCategory[MemberNames::URL_REWRITE_ID]);
            $this->assertSame((integer) $product[MemberNames::ENTITY_ID], (integer) $urlRewriteProductCategory[MemberNames::PRODUCT_ID]);
            $this->assertSame(2, (integer) $urlRewriteProductCategory[MemberNames::CATEGORY_ID]);
        }
    }
}