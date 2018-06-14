<?php

namespace WebcuMarketplacePickwareSets\Components;


use Doctrine\ORM\EntityManagerInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ListProduct;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Detail;
use Shopware\Models\Plugin\Plugin;
use Shopware_Components_Snippet_Manager;
use WebcuMarketplaceConnector\Models\CustomAttributeInterface;

class PickwareSetsStock implements CustomAttributeInterface
{
    /** @var EntityManagerInterface */
    protected $em;

    /** @var Shopware_Components_Snippet_Manager */
    protected $snippets;

    /**
     * @param EntityManagerInterface              $em
     * @param Shopware_Components_Snippet_Manager $snippets
     * @param string                              $pluginDir
     */
    public function __construct($em, $snippets, $pluginDir)
    {
        $this->em       = $em;
        $this->snippets = $snippets;
        $this->snippets->addConfigDir($pluginDir . '/Resources/snippets');
    }

    /**
     * Whether or not the function should be used.
     *
     * For example: you can return `false` if your depending plugin is not active
     *
     * @return bool
     */
    public function isActive()
    {
        $p = $this->em->getRepository(Plugin::class)
            ->findOneBy(['name' => 'ViisonSetArticles']);
        if ($p === null) {
            return false;
        }

        return $p->getActive();
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->snippets->getNamespace('backend/webcu_marketplace_pickware_sets/main')
            ->get('stock', $this->getKey());
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return 'webcu_marketplace_pickware_sets_stock';
    }

    /**
     * @param Article     $swArticle
     * @param Detail      $swDetail
     * @param ListProduct $listProduct
     * @param array       $detail
     * @return mixed
     */
    public function getValue($swArticle, $swDetail, $listProduct, $detail)
    {
        $attr = $swArticle->getAttribute();
        if ($attr) {
            // Additional check to prevent crashing if dependent plugin is missing
            if (method_exists($attr, 'getViisonSetarticleActive')) {
                if ($attr->getViisonSetarticleActive()) {
                    $sql = 'SELECT MIN(sad.instock DIV savs.quantity) as instock
                            FROM s_articles_details as sad
                            LEFT JOIN s_articles_viison_setarticles as savs ON sad.id = savs.articledetailid
                            WHERE savs.setid = :setArticleId';

                    $calculatedInstock = $this->em->getConnection()->fetchAssoc($sql, [
                        'setArticleId' => $swDetail->getId(),
                    ]);
                    return $calculatedInstock['instock'] ? max(0, $calculatedInstock['instock']) : 0;
                }
            }
        }

        return null;
    }

    /**
     * Can return an array (key-value) of values one can map ("Wertzuordnung")
     *
     * Example: using this, one can allow mapping your custom values to (for example)
     * the limited amount of 'color' values allowed at Amazon.
     *
     * @return array|null
     */
    public function getMappingValues()
    {
        return null;
    }
}