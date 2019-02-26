<?php declare(strict_types=1);

namespace Quanta\Container\Values;

use Psr\Container\ContainerInterface;

use Quanta\Container\Compilation\IndentedStr;

final class Instance implements ValueInterface
{
    /**
     * The name of the class to instantiate.
     *
     * @var string
     */
    private $class;

    /**
     * The array of values used as class constructor arguments.
     *
     * @var \Quanta\Container\Values\ValueInterface[]
     */
    private $arguments;

    /**
     * Constructor.
     *
     * @param string                                    $class
     * @param \Quanta\Container\Values\ValueInterface   ...$arguments
     */
    public function __construct(string $class, ValueInterface ...$arguments)
    {
        $this->class = $class;
        $this->arguments = $arguments;
    }

    /**
     * @inheritdoc
     */
    public function value(ContainerInterface $container)
    {
        $arguments = array_map(function (ValueInterface $argument) use ($container) {
            return $argument->value($container);
        }, $this->arguments);

        return new $this->class(...$arguments);
    }

    /**
     * @inheritdoc
     */
    public function str(string $container): string
    {
        if (count($this->arguments) > 0) {
            $arguments = array_map(function (ValueInterface $argument) use ($container) {
                return $argument->str($container);
            }, $this->arguments);

            return vsprintf('new \%s(%s%s%s)', [
                $this->class,
                PHP_EOL,
                new IndentedStr(implode(',' . PHP_EOL, $arguments)),
                PHP_EOL,
            ]);
        }

        return sprintf('new \%s', $this->class);
    }
}
