<?php declare(strict_types=1);

namespace Junu\ModernBooster\Subscriber;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPageLoadedEvent;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\AbstractProductCrossSellingRoute;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\CrossSellingElementCollection;

class OffcanvasSubscriber implements EventSubscriberInterface
{
    private $systemConfigService;
    private $productRepository;
    private $mappingRepository;
    private $crossSellingLoader;

    public function __construct(
        SystemConfigService $systemConfigService,
        SalesChannelRepository $productRepository,
        EntityRepository $mappingRepository,
        AbstractProductCrossSellingRoute $crossSellingLoader
    ) {
        $this->systemConfigService = $systemConfigService;
        $this->productRepository = $productRepository;
        $this->mappingRepository = $mappingRepository;
        $this->crossSellingLoader = $crossSellingLoader;
    }

    public static function getSubscribedEvents()
    {
        return [
            OffcanvasCartPageLoadedEvent::class => 'onOffcanvasLoaded'
        ];
    }

    private function loadCrossSellingProducts(string $productId, $request, $salesChannelContext): ?SalesChannelProductCollection
    {
        $criteria = new Criteria();
        $crossSellingProductsRouteResponse = $this->crossSellingLoader->load($productId, $request, $salesChannelContext, $criteria);
        $crossSellingCollection = $crossSellingProductsRouteResponse->getObject();

        if ($crossSellingCollection instanceof CrossSellingElementCollection && !empty($crossSellingCollection->getElements())) {
            $crossSellingElements = $crossSellingCollection->getElements();
            $products = [];

            foreach ($crossSellingElements as $element) {
                $products = $element->getProducts();
            }
            return $products;
        }

        return null;
    }

    public function onOffcanvasLoaded(OffcanvasCartPageLoadedEvent $event): void
    {
        $saleChannelContext = $event->getSalesChannelContext();
        $salesChannelId = $saleChannelContext->getSalesChannel()->getId();
        $collector = [];
        $showCrossSelling = $this->systemConfigService->get('JunuModernBooster.config.junuShowCrosssellingProducts', $salesChannelId);
        $lastAddedProduct = $event->getPage()->getCart()->getLineItems()->last();

        if ($lastAddedProduct !== null && $showCrossSelling) {
            $productId = $lastAddedProduct->getReferencedId();
            $request = $event->getRequest();
            $crossSellingProducts = $this->loadCrossSellingProducts($productId, $request, $saleChannelContext);

            if (!empty($crossSellingProducts)) {
                foreach ($crossSellingProducts as $crossSellingProduct) {
                    $collector[$crossSellingProduct->getId()] = $crossSellingProduct->getId();
                }
                $event->getPage()->getCart()->setExtensions(['crossSellingProducts' => $crossSellingProducts]);
            }
        }

        if ($this->systemConfigService->get('JunuModernBooster.config.junuGroup', $salesChannelId)) {
            $junuGroupId = $this->systemConfigService->get('JunuModernBooster.config.junuGroup', $salesChannelId);
            $junuExplode = explode('-', $junuGroupId);

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('productStreamId', $junuExplode[0]));

            $groupProducts = $this->mappingRepository->searchIds($criteria, $event->getContext());
            $groupProductsIds = $groupProducts->getIds();

            foreach ($groupProductsIds as $ad) {
                $collector[$ad['productId']] = $ad['productId'];
            }
        }

        if ($this->systemConfigService->get('JunuModernBooster.config.junuCrossSellingMultiSelect', $salesChannelId) || $this->systemConfigService->get('JunuModernBooster.config.junuGroup', $salesChannelId)) {
            $junuProductIds = $this->systemConfigService->get('JunuModernBooster.config.junuCrossSellingMultiSelect', $salesChannelId);
            if (is_array($junuProductIds)) {
                foreach ($junuProductIds as $id) {
                    $collector[$id] = $id;
                }
            }

            $page = $event->getPage();
            $currentCartItems = $page->getCart()->getLineItems()->getElements();
            $products = $this->getProducts($collector, $currentCartItems, $saleChannelContext);
            $assign = [
                'junuCrossSellingArticles' => $products
            ];

            $page->assign($assign);
        }
    }

    public function getProducts($recommendedArticlesId, $currentCartItems, $context)
    {
        $toDisplayArticlesId = [];
        foreach ($recommendedArticlesId as $junuArticleId) {
            if (!$this->isItemAlreadyInCart($junuArticleId, $currentCartItems, $context)) {
                array_push($toDisplayArticlesId, $junuArticleId);
            }
        }

        if (!empty($toDisplayArticlesId)) {
            $criteria = (new Criteria($toDisplayArticlesId))
                ->addFilter(new EqualsFilter("product.available", true))
                ->addAssociation('cover')
                ->addAssociation('prices') // Needed to detect advanced pricing
                ->addAssociation('customFields');

            $entities = $this->productRepository->search($criteria, $context)->getElements();

            foreach ($entities as $key => &$entity) {
                // Exclude variants and variant parents
                if ($this->isVariantProduct($entity, $context)) {
                    unset($entities[$key]);
                    continue;
                }

                // Check custom field
                $customFields = $entity->getCustomFields();
                if (!empty($customFields['junu_products_steps_active'])) {
                    unset($entities[$key]);
                    continue;
                }

                // Check advanced pricing (non-empty price rules)
                $priceRules = $entity->get('prices');
                if ($priceRules && count($priceRules) > 0) {
                    unset($entities[$key]);
                    continue;
                }

                $entity->assign(['junuIsVariantArticle' => false]);
            }

            return $entities;
        }

        return false;
    }

    public function isItemAlreadyInCart($junuArticleId, $currentCartItems, $context)
    {
        foreach ($currentCartItems as $cartItem) {
            $cartItemProductEntity = $this->productRepository->search(new Criteria([$cartItem->getId()]), $context)->first();

            if ($cartItemProductEntity == null) {
                return false;
            }

            if ($parentId = $cartItemProductEntity->getParentId()) {
                $criteria = (new Criteria())->addFilter(new EqualsFilter('parentId', $parentId));
                $variants = $this->productRepository->search($criteria, $context);

                foreach ($variants->getElements() as $variant) {
                    if ($variant->getId() == $junuArticleId) {
                        return true;
                    }
                }
            }

            if ($cartItem->getId() === $junuArticleId || $cartItemProductEntity->getParentId() === $junuArticleId) {
                return true;
            }
        }

        return false;
    }

    public function isVariantProduct(SalesChannelProductEntity $entity, $context)
    {
        if (!empty($entity->getParentId())) {
            return true;
        }

        $criteria = (new Criteria())->addFilter(new EqualsFilter('parentId', $entity->getId()));
        $result = $this->productRepository->search($criteria, $context);

        return !empty($result->getElements());
    }
}
