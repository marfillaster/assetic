<?php

/*
 * This file is part of the Assetic package.
 *
 * (c) Kris Wallsmith <kris.wallsmith@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Assetic\Factory;

use Assetic\AssetManager;
use Assetic\Factory\Loader\FormulaLoaderInterface;
use Assetic\Factory\Resource\ResourceInterface;

/**
 * A lazy asset manager is a composition of a factory and many formula loaders.
 *
 * @author Kris Wallsmith <kris.wallsmith@gmail.com>
 */
class LazyAssetManager extends AssetManager
{
    private $factory;
    private $loaders;
    private $resources;
    private $formulae;
    private $loaded;

    /**
     * Constructor.
     *
     * @param AssetFactory $factory The asset factory
     * @param array        $loaders An array of loaders indexed by alias
     */
    public function __construct(AssetFactory $factory, $loaders = array())
    {
        $this->factory = $factory;
        $this->loaders = array();
        $this->resources = array();
        $this->formulae = array();
        $this->loaded = false;

        foreach ($loaders as $alias => $loader) {
            $this->addLoader($alias, $loader);
        }
    }

    /**
     * Adds a loader to the asset manager.
     *
     * @param string                 $alias  An alias for the loader
     * @param FormulaLoaderInterface $loader A loader
     */
    public function addLoader($alias, FormulaLoaderInterface $loader)
    {
        $this->loaders[$alias] = $loader;
        $this->loaded = false;
    }

    /**
     * Adds a resource to the asset manager.
     *
     * @param string            $loader   The loader alias for this resource
     * @param ResourceInterface $resource A resource
     */
    public function addResource($loader, ResourceInterface $resource)
    {
        $this->resources[$loader][] = $resource;
        $this->loaded = false;
    }

    /**
     * Loads formulae from resources.
     *
     * @throws LogicException If a resource has been added to an invalid loader
     */
    public function load()
    {
        if ($diff = array_diff(array_keys($this->resources), array_keys($this->loaders))) {
            throw new \LogicException('The following loader(s) are not registered: '.implode(', ', $diff));
        }

        $formulae = array();
        foreach ($this->resources as $loader => $resources) {
            foreach ($resources as $resource) {
                $formulae += $this->loaders[$loader]->load($resource);
            }
        }

        $this->formulae = $formulae;
        $this->loaded = true;
    }

    public function get($name)
    {
        if (!$this->loaded) {
            $this->load();
        }

        if (!parent::has($name) && isset($this->formulae[$name])) {
            list($inputs, $filters, $options) = $this->formulae[$name];
            $options['name'] = $name;
            parent::set($name, $this->factory->createAsset($inputs, $filters, $options));
        }

        return parent::get($name);
    }

    public function has($name)
    {
        if (!$this->loaded) {
            $this->load();
        }

        return isset($this->formulae[$name]) || parent::has($name);
    }

    public function getNames()
    {
        if (!$this->loaded) {
            $this->load();
        }

        return array_unique(array_merge(parent::getNames(), array_keys($this->formulae)));
    }
}
