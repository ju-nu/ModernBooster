<?php declare(strict_types=1);

namespace Junu\ModernBooster\Twig;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * ShippingTwigExtension provides custom Twig functions for shipping-related data.
 *
 * @package Junu\ModernBooster\Twig
 */
class ShippingTwigExtension extends AbstractExtension
{
    /** @var EntityRepository */
    private EntityRepository $ruleRepository;

    /** @var LoggerInterface */
    private LoggerInterface $logger;

    /**
     * Constructor for ShippingTwigExtension.
     *
     * @param EntityRepository $ruleRepository Repository for fetching rule data (rule.repository).
     * @param LoggerInterface $logger Logger for debugging.
     */
    public function __construct(EntityRepository $ruleRepository, LoggerInterface $logger)
    {
        $this->ruleRepository = $ruleRepository;
        $this->logger = $logger;
    }

    /**
     * Registers custom Twig functions.
     *
     * @return array
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('getFreeShippingThreshold', [$this, 'getFreeShippingThreshold']),
        ];
    }

    /**
     * Fetches the free shipping threshold for the active shipping method.
     *
     * @param Delivery|null $activeShipping The active shipping delivery (page.cart.deliveries.elements|first).
     * @param SalesChannelContext $salesChannelContext The Sales Channel context.
     *
     * @return float The free shipping threshold in cents (default: 5000 cents = 50 EUR).
     */
    public function getFreeShippingThreshold(?Delivery $activeShipping, SalesChannelContext $salesChannelContext): float
    {
        $threshold = 5000; // Default threshold: 50 EUR in cents

        // Log initial state
        $this->logger->debug('Fetching free shipping threshold', [
            'hasActiveShipping' => $activeShipping !== null,
            'hasShippingMethod' => $activeShipping && $activeShipping->getShippingMethod() !== null,
        ]);

        if (!$activeShipping || !$activeShipping->getShippingMethod()) {
            $this->logger->warning('No active shipping method found');
            return $threshold;
        }

        /** @var ShippingMethodEntity $shippingMethod */
        $shippingMethod = $activeShipping->getShippingMethod();

        // Log shipping method details
        $this->logger->debug('Active shipping method found', [
            'shippingMethodId' => $shippingMethod->getId(),
            'availabilityRuleId' => $shippingMethod->getAvailabilityRuleId(),
            'customFields' => $shippingMethod->getCustomFields(),
        ]);

        // Check availability rule for the threshold
        if ($shippingMethod->getAvailabilityRuleId()) {
            $criteria = new Criteria([$shippingMethod->getAvailabilityRuleId()]);
            $rule = $this->ruleRepository->search($criteria, $salesChannelContext->getContext())->first();

            if ($rule && $rule->getConditions()) {
                $this->logger->debug('Rule found', ['ruleId' => $rule->getId(), 'conditions' => $rule->getConditions()->getElements()]);
                foreach ($rule->getConditions() as $condition) {
                    if ($condition->getType() === 'cartAmountAbsolute' && isset($condition->getValue()['minValue'])) {
                        $threshold = $condition->getValue()['minValue'] * 100; // Convert to cents
                        $this->logger->info('Threshold found in availability rule', ['threshold' => $threshold]);
                        return (float) $threshold;
                    }
                }
            } else {
                $this->logger->warning('No rule or conditions found for availabilityRuleId', ['availabilityRuleId' => $shippingMethod->getAvailabilityRuleId()]);
            }
        }

        // Fallback to custom fields if available
        $customFields = $shippingMethod->getCustomFields();
        if ($customFields && isset($customFields['free_shipping_threshold'])) {
            $threshold = $customFields['free_shipping_threshold'];
            $this->logger->info('Threshold found in custom fields', ['threshold' => $threshold]);
            return (float) $threshold;
        }

        $this->logger->warning('Falling back to default threshold', ['threshold' => $threshold]);
        return (float) $threshold;
    }
}