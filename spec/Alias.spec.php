<?php

use function Eloquent\Phony\Kahlan\mock;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

use Quanta\Container\Alias;
use Quanta\Container\FactoryInterface;
use Quanta\Container\Compilation\Compiler;

describe('Alias', function () {

    context('when there is no nullable boolean', function () {

        it('should be an alias with the id and nullable set to false', function () {

            $test = new Alias('id');

            expect($test)->toEqual(new Alias('id', false));

        });

    });

    context('when there is a nullable boolean', function () {

        context('when the nullable boolean is set to false', function () {

            beforeEach(function () {

                $this->factory = new Alias('id', false);

            });

            it('should implement FactoryInterface', function () {

                expect($this->factory)->toBeAnInstanceOf(FactoryInterface::class);

            });

            describe('->__invoke()', function () {

                it('should return the container entry associated with the id', function () {

                    $container = mock(ContainerInterface::class);

                    $container->get->with('id')->returns('value');

                    $test = ($this->factory)($container->get());

                    expect($test)->toEqual('value');

                });

            });

            describe('->compilable()', function () {

                it('should return a compilable version of the alias', function () {

                    $compiler = Compiler::testing();

                    $test = $this->factory->compilable('container');

                    expect($compiler($test))->toEqual('$container->get(\'id\')');

                });

            });

        });

        context('when the nullable boolean is set to true', function () {

            beforeEach(function () {

                $this->factory = new Alias('id', true);

            });

            it('should implement FactoryInterface', function () {

                expect($this->factory)->toBeAnInstanceOf(FactoryInterface::class);

            });

            describe('->__invoke()', function () {

                beforeEach(function () {

                    $this->container = mock(ContainerInterface::class);

                });

                context('when the container does not contains id', function () {

                    it('should return null', function () {

                        $this->container->has->with('id')->returns(false);

                        $test = ($this->factory)($this->container->get());

                        expect($test)->toBeNull();

                    });

                });

                context('when the container contains id', function () {

                    beforeEach(function () {

                        $this->container->has->with('id')->returns(true);

                    });

                    context('when the container does not throw an exception', function () {

                        it('should return the container entry', function () {

                            $this->container->get->with('id')->returns('value');

                            $test = ($this->factory)($this->container->get());

                            expect($test)->toEqual('value');

                        });

                    });

                    context('when the container does throw an exception', function () {

                        context('when the exception is a NotFoundExceptionInterface', function () {

                            it('should return null', function () {

                                $this->container->get->with('id')->throws(mock([
                                    Throwable::class,
                                    NotFoundExceptionInterface::class,
                                ]));

                                $test = ($this->factory)($this->container->get());

                                expect($test)->toBeNull();

                            });

                        });

                        context('when the exception is not a NotFoundExceptionInterface', function () {

                            it('should rethrow the exception', function () {

                                $exception = mock(Throwable::class);

                                $this->container->get->with('id')->throws($exception);

                                $test = function () {
                                    ($this->factory)($this->container->get());
                                };

                                expect($test)->toThrow($exception->get());

                            });

                        });

                    });

                });

            });

            describe('->compilable()', function () {

                it('should return a compilable version of the nullable alias', function () {

                    $compiler = Compiler::testing();

                    $test = $this->factory->compilable('container');

                    expect($compiler($test))->toEqual(implode(PHP_EOL, [
                        '(function ($container) {',
                        '    if ($container->has(\'id\')) {',
                        '        try { return $container->get(\'id\'); }',
                        '        catch (Psr\Container\NotFoundExceptionInterface $e) { return null; }',
                        '    }',
                        '    return null;',
                        '})($container)',
                    ]));

                });

            });

        });

    });

});
