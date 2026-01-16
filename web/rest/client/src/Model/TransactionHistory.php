<?php

declare(strict_types=1);

namespace Model;

use Ivoz\Api\Core\Annotation\AttributeDefinition;

/**
 * Response model for GET /balance/history endpoint
 * @codeCoverageIgnore
 */
class TransactionHistory
{
    /**
     * @var Transaction[]
     * @AttributeDefinition(type="array", class="Model\Transaction")
     */
    protected $transactions;

    /**
     * @var Pagination
     * @AttributeDefinition(type="object", class="Model\Pagination")
     */
    protected $pagination;

    public function __construct(array $transactions, Pagination $pagination)
    {
        $this->transactions = $transactions;
        $this->pagination = $pagination;
    }

    /** @return Transaction[] */
    public function getTransactions(): array { return $this->transactions; }

    /** @return Pagination */
    public function getPagination(): Pagination { return $this->pagination; }
}

/**
 * @codeCoverageIgnore
 */
class Transaction
{
    /**
     * @var int
     * @AttributeDefinition(type="int")
     */
    protected $id;

    /**
     * @var float
     * @AttributeDefinition(type="float")
     */
    protected $amount;

    /**
     * @var float
     * @AttributeDefinition(type="float")
     */
    protected $balanceAfter;

    /**
     * @var string
     * @AttributeDefinition(type="string")
     */
    protected $createdAt;

    /**
     * @var string|null
     * @AttributeDefinition(type="string")
     */
    protected $reference;

    public function __construct(int $id, float $amount, float $balanceAfter, string $createdAt, ?string $reference = null)
    {
        $this->id = $id;
        $this->amount = $amount;
        $this->balanceAfter = $balanceAfter;
        $this->createdAt = $createdAt;
        $this->reference = $reference;
    }

    /** @return int */
    public function getId(): int { return $this->id; }
    /** @return float */
    public function getAmount(): float { return $this->amount; }
    /** @return float */
    public function getBalanceAfter(): float { return $this->balanceAfter; }
    /** @return string */
    public function getCreatedAt(): string { return $this->createdAt; }
    /** @return string|null */
    public function getReference(): ?string { return $this->reference; }

    public static function fromBalanceMovement(\Ivoz\Provider\Domain\Model\BalanceMovement\BalanceMovementInterface $movement): self
    {
        return new self(
            $movement->getId(),
            $movement->getAmount() ?? 0.0,
            $movement->getBalance() ?? 0.0,
            $movement->getCreatedOn()?->format(\DateTimeInterface::ATOM) ?? ''
        );
    }
}

/**
 * @codeCoverageIgnore
 */
class Pagination
{
    /**
     * @var int
     * @AttributeDefinition(type="int")
     */
    protected $page;

    /**
     * @var int
     * @AttributeDefinition(type="int")
     */
    protected $perPage;

    /**
     * @var int
     * @AttributeDefinition(type="int")
     */
    protected $total;

    public function __construct(int $page, int $perPage, int $total)
    {
        $this->page = $page;
        $this->perPage = $perPage;
        $this->total = $total;
    }

    /** @return int */
    public function getPage(): int { return $this->page; }
    /** @return int */
    public function getPerPage(): int { return $this->perPage; }
    /** @return int */
    public function getTotal(): int { return $this->total; }
}
