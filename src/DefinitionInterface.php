<?php declare(strict_types=1);

namespace Quanta\Container;

interface DefinitionInterface
{
    /**
     * Return a container factory.
     *
     * @return \Quanta\Container\FactoryInterface
     */
    public function factory(): FactoryInterface;
}
