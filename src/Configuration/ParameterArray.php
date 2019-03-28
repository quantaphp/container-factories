<?php declare(strict_types=1);

namespace Quanta\Container\Configuration;

use Quanta\Container\ParameterFactoryMap;
use Quanta\Container\Values\ValueFactory;

final class ParameterArray implements ConfigurationInterface
{
    /**
     * The value factory used to parse parameters.
     *
     * @var \Quanta\Container\Values\ValueFactory
     */
    private $factory;

    /**
     * The array of parameters to provide.
     *
     * @var array
     */
    private $parameters;

    /**
     * Constructor.
     *
     * @param \Quanta\Container\Values\ValueFactory $factory
     * @param array                                 $parameters
     */
    public function __construct(ValueFactory $factory, array $parameters)
    {
        $this->factory = $factory;
        $this->parameters = $parameters;
    }

    /**
     * @inheritdoc
     */
    public function unit(): ConfigurationUnitInterface
    {
        return new ConfigurationUnit(
            new ParameterFactoryMap($this->factory, $this->parameters)
        );
    }
}
