<?php

namespace Model;

use Ivoz\Api\Core\Annotation\AttributeDefinition;

/**
 * Available DID Countries response
 * @codeCoverageIgnore
 */
class AvailableDdiCountry
{
    /**
     * @var array
     * @AttributeDefinition(type="array")
     */
    protected $countries;

    /**
     * @var array
     * @AttributeDefinition(type="array")
     */
    protected $types;

    /**
     * @var array
     * @AttributeDefinition(type="array")
     */
    protected $priceRange;
}
