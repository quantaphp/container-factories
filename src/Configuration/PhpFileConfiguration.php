<?php declare(strict_types=1);

namespace Quanta\Container\Configuration;

use Quanta\Container\FactoryMap;
use Quanta\Container\TaggingPass;
use Quanta\Container\ExtensionPass;
use Quanta\Container\ProcessedFactoryMap;
use Quanta\Container\ProcessingPassInterface;
use Quanta\Container\Values\ValueFactory;
use Quanta\Container\Tagging;
use Quanta\Container\Factories\Tag;
use Quanta\Container\Factories\Alias;
use Quanta\Container\Factories\Factory;
use Quanta\Container\Factories\Invokable;
use Quanta\Container\Factories\Extension;

final class PhpFileConfiguration implements ConfigurationInterface
{
    /**
     * The value factory used to parse parameters.
     *
     * @var \Quanta\Container\Values\ValueFactory
     */
    private $factory;

    /**
     * The php file path.
     *
     * @var string
     */
    private $path;

    /**
     * Constructor.
     *
     * @param \Quanta\Container\Values\ValueFactory $factory
     * @param string                                $path
     */
    public function __construct(ValueFactory $factory, string $path)
    {
        $this->factory = $factory;
        $this->path = $path;
    }

    /**
     * @inheritdoc
     */
    public function map(): ProcessedFactoryMap
    {
        // ensure the file exists.
        if (! file_exists($this->path)) {
            throw new \RuntimeException(
                sprintf('No PHP configuration file located at %s', $this->path)
            );
        }

        // get the content of the file, hide non php contents.
        $contents = require $this->path;

        // ensure the file returns an array.
        if (! is_array($contents)) {
            throw new \UnexpectedValueException(
                vsprintf('PHP configuration file must return an array, %s returned (see %s)', [
                    gettype($contents),
                    $this->path,
                ])
            );
        }

        // get the sanitized configuration.
        $result = \Quanta\ArrayTypeCheck::nested($contents, [
            'parameters' => '*',
            'aliases' => 'string',
            'invokables' => 'string',
            'factories' => 'callable',
            'extensions' => 'callable',
            'tags.*' => 'string',
            'mappers' => 'string',
            'passes' => ProcessingPassInterface::class,
        ]);

        if (! $result->isValid()) {
            throw new \UnexpectedValueException(
                sprintf('%s (see %s)', $result->message()->source('configuration array'), $this->path)
            );
        }

        $configuration = $result->sanitized();

        // Get the parameters values.
        $parameters = array_map($this->factory, $configuration['parameters']);

        // Build factories.
        $factories[] = array_map([Factory::class, 'instance'], $parameters);
        $factories[] = array_map([Alias::class, 'instance'], $configuration['aliases']);
        $factories[] = array_map([Invokable::class, 'instance'], $configuration['invokables']);
        $factories[] = $configuration['factories'];

        // Build passes.
        $passes[] = array_map([ExtensionPass::class, 'instance'], ...[
            array_keys($configuration['extensions']),
            $configuration['extensions'],
        ]);

        $passes[] = array_map([$this, 'taggingPass'], ...[
            array_keys($configuration['tags']),
            $configuration['tags'],
        ]);

        $passes[] = array_map([$this, 'reverseTaggingPass'], ...[
            array_keys($configuration['mappers']),
            $configuration['mappers'],
        ]);

        $factories[] = array_values($configuration['passes']);

        // Return the processed factory map.
        return new ProcessedFactoryMap(
            new FactoryMap(array_merge(...$factories)),
            ...array_merge(...$passes)
        );
    }

    /**
     * Return a tagging pass associating the given id to the entries with the
     * given ids (manual tagging).
     *
     * @param string    $id
     * @param string[]  $ids
     * @return \Quanta\Container\TaggingPass
     */
    private function taggingPass(string $id, array $ids): TaggingPass
    {
        return new TaggingPass($id, new Tagging\IsInList(...array_values($ids)));
    }

    /**
     * Return a tagging pass associating the given id to the entries with a
     * subclass of the given class as id (reverse tagging).
     *
     * @param string $id
     * @param string $class
     * @return \Quanta\Container\TaggingPass
     */
    private function reverseTaggingPass(string $id, string $class): TaggingPass
    {
        return new TaggingPass($id, new Tagging\IsSubclassOf($class));
    }
}