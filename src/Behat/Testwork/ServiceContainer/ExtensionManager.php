<?php

/*
 * This file is part of the Behat Testwork.
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Behat\Testwork\ServiceContainer;

use Behat\Testwork\ServiceContainer\Exception\ExtensionInitializationException;

/**
 * Testwork extension manager.
 *
 * Manages both default and 3rd-party extensions.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
class ExtensionManager
{
    /**
     * @var string
     */
    private $extensionsPath;
    /**
     * @var Extension[]
     */
    private $extensions = array();
    /**
     * @var Extension[string]
     */
    private $locatedExtensions = array();

    /**
     * Initializes manager.
     *
     * @param Extension[] $extensions     List of default extensions
     * @param null|string $extensionsPath Base path where to search custom extension files
     */
    public function __construct(array $extensions, $extensionsPath = null)
    {
        foreach ($extensions as $extension) {
            $this->extensions[$extension->getConfigKey()] = $extension;
        }

        $this->extensionsPath = $extensionsPath;
    }

    /**
     * Sets path to directory in which manager will try to find extension files.
     *
     * @param null|string $path
     */
    public function setExtensionsPath($path)
    {
        $this->extensionsPath = $path;
    }

    /**
     * Activate extension by its locator.
     *
     * @param string $locator phar file name, php file name, class name
     *
     * @return Extension
     */
    public function activateExtension($locator)
    {
        $extension = $this->initialize($locator);

        return $this->extensions[$extension->getConfigKey()] = $extension;
    }

    /**
     * Returns specific extension by its name.
     *
     * @param string $key
     *
     * @return Extension
     */
    public function getExtension($key)
    {
        return isset($this->extensions[$key]) ? $this->extensions[$key] : null;
    }

    /**
     * Returns all available extensions.
     *
     * @return Extension[]
     */
    public function getExtensions()
    {
        return $this->extensions;
    }

    /**
     * Returns activated extension names.
     *
     * @return array
     */
    public function getExtensionClasses()
    {
        return array_map('get_class', array_values($this->extensions));
    }

    /**
     * Attempts to guess full extension class from relative.
     *
     * @param string $locator
     *
     * @return string
     */
    protected function getFullExtensionClass($locator)
    {
        $parts = explode('\\', $locator);

        return $locator . '\\ServiceContainer\\' . end($parts) . 'Extension';
    }

    /**
     * Initializes extension by id.
     *
     * @param string $locator
     *
     * @return Extension
     *
     * @throws ExtensionInitializationException
     */
    private function initialize($locator)
    {
        if (isset($this->locatedExtensions[$locator])) {
            return $this->locatedExtensions[$locator];
        }

        $extension = null;
        if (class_exists($class = $locator)) {
            $extension = new $class;
        } elseif (class_exists($class = $this->getFullExtensionClass($locator))) {
            $extension = new $class;
        } elseif (file_exists($path = $this->extensionsPath . DIRECTORY_SEPARATOR . $locator)) {
            $extension = require($path);
        } else {
            $extension = require($locator);
        }

        if (null === $extension) {
            throw new ExtensionInitializationException(sprintf(
                '`%s` extension could not be found.',
                $locator
            ), $locator);
        }
        if (!is_object($extension)) {
            throw new ExtensionInitializationException(sprintf(
                '`%s` extension could not be initialized.',
                $locator
            ), $locator);
        }
        if (!$extension instanceof Extension) {
            throw new ExtensionInitializationException(sprintf(
                '`%s` extension class should implement Testwork Extension interface.',
                get_class($extension)
            ), $locator);
        }

        return $this->locatedExtensions[$locator] = $extension;
    }
}
