<?php declare(strict_types=1);

namespace Junu\ModernBooster\Twig;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * ProductTwigExtension provides custom Twig functions related to products.
 *
 * @package Junu\ModernBooster\Twig
 */
class ProductTwigExtension extends AbstractExtension
{
    /** @var SalesChannelRepository */
    private SalesChannelRepository $productRepository;

    /** @var UrlGeneratorInterface */
    private UrlGeneratorInterface $urlGenerator;

    /**
     * Constructor for ProductTwigExtension.
     *
     * @param SalesChannelRepository $productRepository Repository for fetching product data (sales_channel.product.repository).
     * @param UrlGeneratorInterface $urlGenerator URL generator for creating product URLs.
     */
    public function __construct(
        SalesChannelRepository $productRepository,
        UrlGeneratorInterface $urlGenerator
    ) {
        $this->productRepository = $productRepository;
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * Registers custom Twig functions.
     *
     * @return array
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('searchProduct', [$this, 'searchProduct']),
        ];
    }

    /**
     * Searches for a product by its ID and returns relevant data including a safe URL.
     *
     * @param string $productId The ID of the product to search.
     * @param SalesChannelContext $salesChannelContext The Sales Channel context.
     *
     * @return array|null Array containing product entity and URL or null if not found.
     */
    public function searchProduct(string $productId, SalesChannelContext $salesChannelContext): ?array
    {
        if (empty($productId) || !Uuid::isValid($productId)) {
            return null;
        }

        try {
            // Prepare criteria to fetch product with necessary associations
            $criteria = new Criteria([$productId]);
            $criteria->addAssociation('unit');
            $criteria->addAssociation('calculatedPrice');
            $criteria->addAssociation('seoUrls', function (Criteria $seoUrlCriteria) {
                $seoUrlCriteria->addFilter(new EqualsFilter('isCanonical', true));
                $seoUrlCriteria->addFilter(new EqualsFilter('isDeleted', false));
                $seoUrlCriteria->addFilter(new EqualsFilter('isValid', true));
                $seoUrlCriteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
            });

            /** @var SalesChannelProductEntity|null $product */
            $product = $this->productRepository->search($criteria, $salesChannelContext)->first();

            if (!$product instanceof SalesChannelProductEntity) {
                return null;
            }

            // Generate default fallback URL
            $productUrl = $this->urlGenerator->generate(
                'frontend.detail.page',
                ['productId' => $product->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            // Safely extract SEO URL
            $seoUrls = $product->getSeoUrls();

            if ($seoUrls && $seoUrls->count() > 0) {
                foreach ($seoUrls as $seoUrlEntity) {
                    $url = $seoUrlEntity->get('url');
                    if (is_string($url) && !empty($url)) {
                        $productUrl = $url;
                        break;
                    }
                }
            }

            return [
                'product' => $product,
                'productUrl' => $productUrl,
            ];
        } catch (\Exception $e) {
            // Log the error for debugging
            error_log('ProductTwigExtension Error: ' . $e->getMessage());
            return null;
        }
    }
}