<?php declare(strict_types=1);

namespace Quanta\Container\Configuration;

use Quanta\Container\ValueParser;

final class ParameterArray implements ConfigurationInterface
{
    /**
     * The parser used to produce factories from parameters.
     *
     * @var \Quanta\Container\ValueParser
     */
    private $parser;

    /**
     * The array of parameters to provide.
     *
     * @var array
     */
    private $parameters;

    /**
     * Constructor.
     *
     * @param \Quanta\Container\ValueParser $parser
     * @param array                         $parameters
     */
    public function __construct(ValueParser $parser, array $parameters)
    {
        $this->parser = $parser;
        $this->parameters = $parameters;
    }

    /**
     * @inheritdoc
     */
    public function factories(): array
    {
        return array_map($this->parser, $this->parameters);
    }

    /**
     * @inheritdoc
     */
    public function mappers(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function extensions(): array
    {
        return [];
    }
}
