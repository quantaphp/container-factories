<?php declare(strict_types=1);

namespace Quanta\Container;

use Interop\Container\ServiceProviderInterface;

use Quanta\Container\Factories\Tag;
use Quanta\Container\Factories\Alias;
use Quanta\Container\Factories\Parameter;

use Quanta\Container\Values\ValueFactoryInterface;

use function Quanta\Exceptions\areAllTypedAs;
use Quanta\Exceptions\InvalidKey;
use Quanta\Exceptions\InvalidType;
use Quanta\Exceptions\ReturnTypeErrorMessage;
use Quanta\Exceptions\ArrayReturnTypeErrorMessage;

final class PhpFileConfiguration implements ConfigurationInterface
{
    /**
     * The expected key names of the arrays returned by the files.
     *
     * @var string[]
     */
    const KEYS = [
        'parameters' => 'parameters',
        'aliases' => 'aliases',
        'factories' => 'factories',
        'extensions' => 'extensions',
        'tags' => 'tags',
    ];

    /**
     * The value factory used to parse parameter values as ValueInterface
     * implementations.
     *
     * @var \Quanta\Container\Values\ValueFactoryInterface
     */
    private $factory;

    /**
     * Glob patterns matching files returning array of factories.
     *
     * @var string[]
     */
    private $patterns;

    /**
     * Constructor.
     *
     * @param \Quanta\Container\Values\ValueFactoryInterface    $factory
     * @param string                                            ...$patterns
     */
    public function __construct(ValueFactoryInterface $factory, string ...$patterns)
    {
        $this->factory = $factory;
        $this->patterns = $patterns;
    }

    /**
     * @inheritdoc
     */
    public function providers(): array
    {
        foreach ($this->patterns as $pattern) {
            foreach (glob($pattern) as $path) {
                $config = require $path;

                if (! is_array($config)) {
                    throw new \UnexpectedValueException(
                        (string) new ReturnTypeErrorMessage(
                            sprintf('the file located at %s', $path), 'array', $config
                        )
                    );
                }

                if (! areAllTypedAs('array', $config)) {
                    throw new \UnexpectedValueException(
                        (string) new ArrayReturnTypeErrorMessage(
                            sprintf('the file located at %s', $path), 'array', $config
                        )
                    );
                }

                // give default values to missing keys.
                $config = [
                    'parameters' => $config[self::KEYS['parameters']] ?? [],
                    'aliases' => $config[self::KEYS['aliases']] ?? [],
                    'factories' => $config[self::KEYS['factories']] ?? [],
                    'extensions' => $config[self::KEYS['extensions']] ?? [],
                    'tags' => $config[self::KEYS['tags']] ?? [],
                ];

                // build factory maps from the definitions.
                $parameters = $this->parameters($config['parameters']);
                $aliases = $this->aliases($path, $config['aliases']);
                $factories = $this->factories($path, $config['factories']);
                $extensions = $this->extensions($path, $config['extensions']);
                $tags = $this->tags($path, $config['tags']);

                // add an anonymous tagged service provider.
                $providers[] = $this->provider(...[
                    new MergedFactoryMap($parameters, $aliases, $factories),
                    new MergedFactoryMap($extensions, $tags),
                    array_merge(...[
                        $this->tagsFromIds($config['aliases']),
                        $config['tags'],
                    ]),
                ]);
            }
        }

        return $providers ?? [];
    }

    /**
     * Return a parameter from the given value using the factory to parse it as
     * a ValueInterface implementation.
     *
     * @param mixed $value
     * @return \Quanta\Container\Factories\Parameter
     */
    private function parameter($value): Parameter
    {
        return new Parameter(($this->factory)($value));
    }

    /**
     * Return an array of parameters from the given array.
     *
     * @param array $values
     * @return \Quanta\Container\FactoryMap
     */
    private function parameters(array $values): FactoryMap
    {
        return new FactoryMap(array_map([$this, 'parameter'], $values));
    }

    /**
     * Return an alias from the given container entry id.
     *
     * @param string $id
     * @return \Quanta\Container\Factories\Alias
     */
    private function alias(string $id): Alias
    {
        return new Alias($id);
    }

    /**
     * Return an array of aliases from the given array of container entry ids.
     *
     * The file path is given in order to throw a descriptive exception.
     *
     * @param string $path
     * @param array $aliases
     * @return \Quanta\Container\FactoryMap
     * @throws \UnexpectedValueException
     */
    private function aliases(string $path, array $aliases): FactoryMap
    {
        try {
            return new FactoryMap(array_map([$this, 'alias'], $aliases));
        }

        catch (\TypeError $e) {
            throw new \UnexpectedValueException(
                $this->invalidKeyTypeErrorMessage(
                    $path, self::KEYS['aliases'], 'string', $aliases
                )
            );
        }
    }

    /**
     * Ensure all values of the given array of factories are callable.
     *
     * The file path is given in order to throw a descriptive exception.
     *
     * @param string $path
     * @param array $factories
     * @return \Quanta\Container\FactoryMap
     * @throws \UnexpectedValueException
     */
    private function factories(string $path, array $factories): FactoryMap
    {
        try {
            return new FactoryMap($factories);
        }

        catch (\InvalidArgumentException $e) {
            throw new \UnexpectedValueException(
                $this->invalidKeyTypeErrorMessage(
                    $path, self::KEYS['factories'], 'callable', $factories
                )
            );
        }
    }

    /**
     * Ensure all values of the given array of extensions are callable.
     *
     * The file path is given in order to throw a descriptive exception.
     *
     * @param string $path
     * @param array $extensions
     * @return \Quanta\Container\FactoryMap
     * @throws \UnexpectedValueException
     */
    private function extensions(string $path, array $extensions): FactoryMap
    {
        try {
            return new FactoryMap($extensions);
        }

        catch (\InvalidArgumentException $e) {
            throw new \UnexpectedValueException(
                $this->invalidKeyTypeErrorMessage(
                    $path, self::KEYS['extensions'], 'callable', $extensions
                )
            );
        }
    }

    /**
     * Return an array of tags from the given array of container entry
     * identifier arrays.
     *
     * The file path is given in order to throw a descriptive exception.
     *
     * @param string $path
     * @param array $tags
     * @return \Quanta\Container\FactoryMap
     * @throws \UnexpectedValueException
     */
    private function tags(string $path, array $tags): FactoryMap
    {
        if (! areAllTypedAs('array', $tags)) {
            throw new \UnexpectedValueException(
                $this->invalidKeyTypeErrorMessage(
                    $path, self::KEYS['tags'], 'array', $tags
                )
            );
        }

        foreach ($tags as $id => $tag) {
            if (! areAllTypedAs('array', $tag)) {
                throw new \UnexpectedValueException(
                    $this->invalidTagTypeErrorMessage($path, $id, $tag)
                );
            }

            $aliases = array_keys($tag);
            $aliases = array_map('strval', $aliases);

            $extensions[$id] = new Tag(...$aliases);
        }

        return new FactoryMap($extensions ?? []);
    }

    /**
     * Return a tag definition from the given identifier.
     *
     * @param string $id
     * @return array[]
     */
    private function tagFromId(string $id): array
    {
        return [$id => []];
    }

    /**
     * Return a tag definition list from the given array of identifiers.
     *
     * @param string[]  $ids
     * @return array[]
     */
    private function tagsFromIds(array $ids): array
    {
        return array_map([$this, 'tagFromId'], $ids);
    }

    /**
     * Return a tagged service provider with the given factory map, extension
     * map and tags.
     *
     * @param \Quanta\Container\FactoryMapInterface $factories
     * @param \Quanta\Container\FactoryMapInterface $extensions
     * @param array[]                               $tags
     * @return \Quanta\Container\TaggedServiceProviderInterface
     */
    private function provider(FactoryMapInterface $factories, FactoryMapInterface $extensions, array $tags): TaggedServiceProviderInterface
    {
        return new class ($factories, $extensions, $tags) implements TaggedServiceProviderInterface
        {
            private $factories;
            private $extensions;
            private $tags;

            public function __construct($factories, $extensions, $tags)
            {
                $this->factories = $factories;
                $this->extensions = $extensions;
                $this->tags = $tags;
            }

            public function factories(): FactoryMapInterface
            {
                return $this->factories;
            }

            public function extensions(): FactoryMapInterface
            {
                return $this->extensions;
            }

            public function tags(): array
            {
                return $this->tags;
            }
        };
    }

    /**
     * Return the message of exceptions thrown when an array contains at least
     * one value with a wrong type.
     *
     * @param string $path
     * @param string $key
     * @param string $type
     * @param array $values
     * @return string
     */
    private function invalidKeyTypeErrorMessage(string $path, string $key, string $type, array $values): string
    {
        $tpl = implode(' ', [
            'The \'%s\' key of the array returned by the file located at %s',
            'must be an array of %s values,',
            '%s associated to key %s',
        ]);

        return sprintf($tpl, $key, $path, $type, ...[
            new InvalidType($type, $values),
            new InvalidKey($type, $values),
        ]);
    }

    /**
     * Return the message of exceptions thrown when a value of a tag definition
     * array is not a string.
     *
     * @param string        $path
     * @param int|string    $id
     * @param array         $values
     * @return string
     */
    private function invalidTagTypeErrorMessage(string $path, $id, array $values): string
    {
        $tpl = implode(' ', [
            'The tag \'%s\' defined by the file located at %s',
            'must be an array of array values,',
            '%s associated to key %s',
        ]);

        return sprintf($tpl, $id, $path, ...[
            new InvalidType('array', $values),
            new InvalidKey('array', $values),
        ]);
    }
}
