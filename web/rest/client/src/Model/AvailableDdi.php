<?php

namespace Model;

use Ivoz\Api\Core\Annotation\AttributeDefinition;

/**
 * Available DID for marketplace
 * @codeCoverageIgnore
 */
class AvailableDdi
{
    /**
     * @var int
     * @AttributeDefinition(type="int")
     */
    protected $id;

    /**
     * @var string
     * @AttributeDefinition(type="string")
     */
    protected $ddi;

    /**
     * @var string
     * @AttributeDefinition(type="string")
     */
    protected $ddiE164;

    /**
     * @var string|null
     * @AttributeDefinition(type="string")
     */
    protected $description;

    /**
     * @var string
     * @AttributeDefinition(type="string")
     */
    protected $country;

    /**
     * @var int
     * @AttributeDefinition(type="int")
     */
    protected $countryId;

    /**
     * @var string
     * @AttributeDefinition(type="string")
     */
    protected $countryName;

    /**
     * @var string
     * @AttributeDefinition(type="string", description="DDI type: inout, out, or virtual")
     */
    protected $ddiType;

    /**
     * @var string
     * @AttributeDefinition(type="string")
     */
    protected $setupPrice;

    /**
     * @var string
     * @AttributeDefinition(type="string")
     */
    protected $monthlyPrice;

    /**
     * @var string
     * @AttributeDefinition(type="string", description="Inventory status: available, reserved, assigned, suspended, disabled")
     */
    protected $inventoryStatus;
}
