<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\QuoteGraphQl\Model\Resolver;

use Magento\Directory\Block\Currency;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\Cart\Totals;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\Quote\TotalsCollector;

/**
 * @inheritdoc
 */
class CartItemPrices implements ResolverInterface
{
    /**
     * @var TotalsCollector
     */
    private $totalsCollector;

    /**
     * @var Totals
     */
    private $totals;

    /**
     * @param TotalsCollector $totalsCollector
     */
    public function __construct(
        TotalsCollector $totalsCollector
    ) {
        $this->totalsCollector = $totalsCollector;
    }

    /**
     * @inheritdoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, array $value = null, array $args = null)
    {
        if (!isset($value['model'])) {
            throw new LocalizedException(__('"model" value should be specified'));
        }
        /** @var Item $cartItem */
        $cartItem = $value['model'];

        if (!$this->totals) {
            // The totals calculation is based on quote address.
            // But the totals should be calculated even if no address is set
            $this->totals = $this->totalsCollector->collectQuoteTotals($cartItem->getQuote());
        }
        $currencyCode = $cartItem->getQuote()->getQuoteCurrencyCode();

        return [
            'price' => [
                'currency' => $currencyCode,
                'value' => $cartItem->getPrice(),
            ],
            'row_total' => [
                'currency' => $currencyCode,
                'value' => $cartItem->getRowTotal(),
            ],
            'row_total_including_tax' => [
                'currency' => $currencyCode,
                'value' => $cartItem->getRowTotalInclTax(),
            ],
            'discount' => [
                'currency' => $currencyCode,
                'value' => $cartItem->getDiscountAmount(),
            ],
            'discounts' => $this->getDiscountValues($cartItem, $currencyCode)
        ];
    }

    /**
     * Get Discount Values
     *
     * @param Item $cartItem
     * @param Currency $currencyCode
     * @return array
     */
    private function getDiscountValues($cartItem, $currencyCode)
    {
        $discountValues=[];
        foreach ($cartItem->getDiscountBreakdown() as $key => $value) {
            $discount = [];
            $amount = [];
            /**
             * @var \Magento\SalesRule\Model\Rule\Action\Discount\Data $discountData
             */
            $discountData = $value['discount'];
            /**
             * @var \Magento\SalesRule\Model\Rule $rule $rule
             */
            $rule = $value['rule'];
            $discount['label'] = $rule->getStoreLabel($cartItem->getQuote()->getStore());
            $amount['value'] = $discountData->getAmount();
            $amount['currency'] = $currencyCode;
            $discount['amount'] = $amount;
            $discountValues[] = $discount;
        }
        return $discountValues;
    }
}
