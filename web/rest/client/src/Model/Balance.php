<?php

declare(strict_types=1);

namespace Model;

use Ivoz\Api\Core\Annotation\AttributeDefinition;

/**
 * Response model for GET /balance endpoint
 *
 * Contains current balance information and top-up configuration.
 * @codeCoverageIgnore
 */
class Balance
{
    /**
     * @var float
     * @AttributeDefinition(type="float")
     */
    protected $balance;

    /**
     * @var string
     * @AttributeDefinition(type="string")
     */
    protected $currency;

    /**
     * @var string
     * @AttributeDefinition(type="string")
     */
    protected $billingMethod;

    /**
     * @var bool
     * @AttributeDefinition(type="bool")
     */
    protected $showTopUp;

    /**
     * @var float
     * @AttributeDefinition(type="float")
     */
    protected $minAmount;

    /**
     * @var float
     * @AttributeDefinition(type="float")
     */
    protected $maxAmount;

    public function __construct(
        float $balance,
        string $currency,
        string $billingMethod,
        bool $showTopUp,
        float $minAmount = 5.00,
        float $maxAmount = 1000.00
    ) {
        $this->balance = $balance;
        $this->currency = $currency;
        $this->billingMethod = $billingMethod;
        $this->showTopUp = $showTopUp;
        $this->minAmount = $minAmount;
        $this->maxAmount = $maxAmount;
    }

    /**
     * @return float
     */
    public function getBalance(): float
    {
        return $this->balance;
    }

    /**
     * @return string
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * @return string
     */
    public function getBillingMethod(): string
    {
        return $this->billingMethod;
    }

    /**
     * @return bool
     */
    public function getShowTopUp(): bool
    {
        return $this->showTopUp;
    }

    /**
     * @return float
     */
    public function getMinAmount(): float
    {
        return $this->minAmount;
    }

    /**
     * @return float
     */
    public function getMaxAmount(): float
    {
        return $this->maxAmount;
    }

    /**
     * Create from company entity
     */
    public static function fromCompany(
        \Ivoz\Provider\Domain\Model\Company\CompanyInterface $company,
        float $minAmount,
        float $maxAmount
    ): self {
        $billingMethod = $company->getBillingMethod();
        $showTopUp = in_array($billingMethod, ['prepaid', 'pseudoprepaid'], true);

        // Get currency from company or default to EUR
        $currency = 'EUR';
        if (method_exists($company, 'getCurrencySymbol') && $company->getCurrencySymbol()) {
            $currency = $company->getCurrencySymbol();
        }

        return new self(
            $company->getBalance() ?? 0.0,
            $currency,
            $billingMethod,
            $showTopUp,
            $minAmount,
            $maxAmount
        );
    }
}
