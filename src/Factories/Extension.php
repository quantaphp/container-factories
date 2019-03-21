<?php declare(strict_types=1);

namespace Quanta\Container\Factories;

use Psr\Container\ContainerInterface;

final class Extension implements CompilableFactoryInterface
{
    /**
     * The factory to extend.
     *
     * @var callable
     */
    private $factory;

    /**
     * The extension.
     *
     * @var callable
     */
    private $extension;

    /**
     * Return a new Extension from the given factory and extension.
     *
     * @param callable $factory
     * @param callable $extension
     * @return \Quanta\Container\Factories\Extension
     */
    public static function instance(callable $factory, callable $extension): self
    {
        return new self($factory, $extension);
    }

    /**
     * Constructor.
     *
     * @param callable $factory
     * @param callable $extension
     */
    public function __construct(callable $factory, callable $extension)
    {
        $this->factory = $factory;
        $this->extension = $extension;
    }

    /**
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container)
    {
        return ($this->extension)($container, ($this->factory)($container));
    }

    /**
     * @inheritdoc
     */
    public function compiled(Compiler $compiler): CompiledFactory
    {
        return new CompiledFactory('container', ...[
            vsprintf('return (%s)($container, (%s)($container));', [
                $compiler($this->extension),
                $compiler($this->factory),
            ]),
        ]);
    }
}
