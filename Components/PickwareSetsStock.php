<?php

namespace WebcuMarketplacePickwareSets\Components;


use Doctrine\ORM\EntityManagerInterface;
use Shopware\Models\Plugin\Plugin;
use Shopware_Components_Snippet_Manager;
use WebcuMarketplaceConnector\Bridge\Shopware\Article\AttributeInterface;
use WebcuMarketplaceConnector\Bridge\Shopware\Article\RequestOptions;
use WebcuMarketplaceConnector\Models\ColumnConfig;

class PickwareSetsStock implements AttributeInterface
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
     * This key should usually be prefixed by your plugin technical name, to prevent collision.
     *
     * @return string
     */
    public function getKey()
    {
        return 'webcu_marketplace_pickware_sets_stock';
    }

    /**
     * @param RequestOptions $o
     * @return mixed
     */
    public function getValue(RequestOptions $o)
    {
        $attr = $o->getArticle()->getAttribute();
        if ($attr) {
            // Additional check to prevent crashing if dependent plugin is missing
            if (method_exists($attr, 'getViisonSetarticleActive')) {
                if ($attr->getViisonSetarticleActive()) {
                    $sql = 'SELECT MIN(sad.instock DIV savs.quantity) as instock
                            FROM s_articles_details as sad
                            LEFT JOIN s_articles_viison_setarticles as savs ON sad.id = savs.articledetailid
                            WHERE savs.setid = :setArticleId';

                    $calculatedInstock = $this->em->getConnection()->fetchAssoc($sql, [
                        'setArticleId' => $o->getDetail()->getId(),
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

    /**
     * Indicates which MAPPING_* this one is categorized in
     *
     * @return int
     * @see ColumnConfig
     */
    public function getType()
    {
        return ColumnConfig::MAPPING_PLUGIN;
    }

    /**
     * Indicates which <optgroup> this attribute should be listed in.
     *
     * When offering more than one in your plugin, you might want to group them by function (or by plugin).
     *
     * @return string|null
     */
    public function getGroup()
    {
        return null;
    }
}