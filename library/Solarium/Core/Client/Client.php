<?php
/**
 * Copyright 2011 Bas de Nooijer. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *    this listof conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDER AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * The views and conclusions contained in the software and documentation are
 * those of the authors and should not be interpreted as representing official
 * policies, either expressed or implied, of the copyright holder.
 *
 * @copyright Copyright 2011 Bas de Nooijer <solarium@raspberry.nl>
 * @license http://github.com/basdenooijer/solarium/raw/master/COPYING
 * @link http://www.solarium-project.org/
 *
 * @package Solarium
 * @subpackage Core
 */

/**
 * @namespace
 */
namespace Solarium\Core\Client;
use Solarium\Core\Exception;
use Solarium\Core\Configurable;
use Solarium\Core\Plugin;
use Solarium\Core\Query\Query;
use Solarium\Core\Query\Result;

/**
 * Main interface for interaction with Solr
 *
 * The client is the main interface for usage of the Solarium library.
 * You can use it to get query instances and to execute them.
 * It also allows to register plugins and querytypes to customize Solarium.
 * Finally, it also gives access to the adapter, which holds the Solr connection settings.
 *
 * Example usage with default settings:
 * <code>
 * $client = new Solarium\Client;
 * $query = $client->createSelect();
 * $result = $client->select($query);
 * </code>
 *
 * @package Solarium
 * @subpackage Core
 */
class Client extends Configurable
{

    /**
     * Querytype select
     */
    const QUERY_SELECT = 'select';

    /**
     * Querytype update
     */
    const QUERY_UPDATE = 'update';

    /**
     * Querytype ping
     */
    const QUERY_PING = 'ping';

    /**
     * Querytype morelikethis
     */
    const QUERY_MORELIKETHIS = 'mlt';

    /**
     * Querytype analysis field
     */
    const QUERY_ANALYSIS_FIELD = 'analysis-field';

    /**
     * Querytype analysis document
     */
    const QUERY_ANALYSIS_DOCUMENT = 'analysis-document';

    /**
     * Querytype terms
     */
    const QUERY_TERMS = 'terms';

    /**
     * Querytype suggester
     */
    const QUERY_SUGGESTER = 'suggester';

    /**
     * Default options
     *
     * @var array
     */
    protected $options = array(
        'adapter' => 'Solarium\Core\Client\Adapter\Http',
    );

    /**
     * Querytype mappings
     *
     * These can be customized using {@link registerQueryType()}
     */
    protected $queryTypes = array(
        self::QUERY_SELECT => 'Solarium\Query\Select\Query\Query',
        self::QUERY_UPDATE => 'Solarium\Query\Update\Query\Query',
        self::QUERY_PING => 'Solarium\Query\Ping\Query',
        self::QUERY_MORELIKETHIS => 'Solarium\Query\MoreLikeThis\Query',
        self::QUERY_ANALYSIS_DOCUMENT => 'Solarium\Query\Analysis\Query\Document',
        self::QUERY_ANALYSIS_FIELD => 'Solarium\Query\Analysis\Query\Field',
        self::QUERY_TERMS => 'Solarium\Query\Terms\Query',
        self::QUERY_SUGGESTER => 'Solarium\Query\Suggester\Query',
    );

    /**
     * Plugin types
     *
     * @var array
     */
    protected $pluginTypes = array(
        'loadbalancer' => 'Solarium\Plugin\Loadbalancer\Loadbalancer',
        'postbigrequest' => 'Solarium\Plugin\PostBigRequest',
        'customizerequest' => 'Solarium\Plugin\CustomizeRequest\CustomizeRequest',
        'parallelexecution' => 'Solarium\Plugin\ParallelExecution',
        'bufferedadd' => 'Solarium\Plugin\BufferedAdd',
        'prefetchiterator' => 'Solarium\Plugin\PrefetchIterator',
    );

    /**
     * Registered plugin instances
     *
     * @var array
     */
    protected $pluginInstances = array();

    /**
     * Adapter instance
     *
     * If an adapter instance is set using {@link setAdapter()} this var will
     * contain a reference to that instance.
     *
     * In all other cases the adapter is lazy-loading, it will be instantiated
     * on first use by {@link getAdapter()} based on the 'adapter' entry in
     * {@link $options}. This option can be set using {@link setAdapter()}
     *
     * @var Adapter\Adapter
     */
    protected $adapter;

    /**
     * Request builder instances
     *
     * @var array
     */
    protected $requestBuilders;

    /**
     * Initialization hook
     */
    protected function init()
    {
        foreach ($this->options AS $name => $value) {
            switch ($name) {
                case 'querytype':
                    $this->registerQueryTypes($value);
                    break;
                case 'plugin':
                    $this->registerPlugins($value);
                    break;
            }
        }
    }

    /**
     * Set the adapter
     *
     * The adapter has to be a class that extends
     * {@link Solarium\Client\Adapter}.
     *
     *
     * If a string is passed it is assumed to be the classname and it will be
     * instantiated on first use. This requires the availability of the class
     * through autoloading or a manual require before calling this method.
     * Any existing adapter instance will be removed by this method, this way an
     * instance of the new adapter type will be created upon the next usage of
     * the adapter (lazy-loading)
     *
     * If an adapter instance is passed it will replace the current adapter
     * immediately, bypassing the lazy loading.
     *
     * @param string|Adapter\Adapter $adapter
     * @return self Provides fluent interface
     */
    public function setAdapter($adapter)
    {
        if (is_string($adapter)) {
            $this->adapter = null;
            return $this->setOption('adapter', $adapter);
        } else {
            // forward options
            $adapter->setOptions($this->options);
            // overwrite existing adapter
            $this->adapter = $adapter;
            return $this;
        }
    }

    /**
     * Create an adapter instance
     *
     * The 'adapter' entry in {@link $options} will be used to create an
     * adapter instance. This entry can be the default value of
     * {@link $options}, a value passed to the constructor or a value set by
     * using {@link setAdapter()}
     *
     * This method is used for lazy-loading the adapter upon first use in
     * {@link getAdapter()}
     *
     * @return void
     */
    protected function createAdapter()
    {
        $adapterClass = $this->getOption('adapter');
        $this->adapter = new $adapterClass;
        $this->adapter->setOptions($this->getOption('adapteroptions'));
    }

    /**
     * Get the adapter instance
     *
     * If {@see $adapter} doesn't hold an instance a new one will be created by
     * calling {@see _createAdapter()}
     *
     * @param boolean $autoload
     * @return Adapter\Adapter
     */
    public function getAdapter($autoload = true)
    {
        if (null === $this->adapter && $autoload) {
            $this->createAdapter();
        }

        return $this->adapter;
    }

    /**
     * Register a querytype
     *
     * You can also use this method to override any existing querytype with a new mapping.
     * This requires the availability of the classes through autoloading or a manual
     * require before calling this method.
     *
     * @param string $type
     * @param string $queryClass
     * @return self Provides fluent interface
     */
    public function registerQueryType($type, $queryClass)
    {
        $this->queryTypes[$type] = $queryClass;

        return $this;
    }

    /**
     * Register multiple querytypes
     *
     * @param array $queryTypes
     * @return self Provides fluent interface
     */
    public function registerQueryTypes($queryTypes)
    {
        foreach ($queryTypes as $type => $class) {

            // support both "key=>value" and "(no-key) => array(key=>x,query=>y)" formats
            if (is_array($class)) {
                if (isset($class['type'])) $type = $class['type'];
                $class = $class['query'];
            }

            $this->registerQueryType($type, $class);
        }
    }

    /**
     * Get all registered querytypes
     *
     * @return array
     */
    public function getQueryTypes()
    {
        return $this->queryTypes;
    }

    /**
     * Register a plugin
     *
     * You can supply a plugin instance or a plugin classname as string.
     * This requires the availability of the class through autoloading
     * or a manual require.
     *
     * @param string $key
     * @param string|Plugin $plugin
     * @param array $options
     * @return self Provides fluent interface
     */
    public function registerPlugin($key, $plugin, $options = array())
    {
        if (is_string($plugin)) {
            $plugin = class_exists($plugin) ? $plugin : $plugin.strrchr($plugin, '\\');
            $plugin = new $plugin;
        }

        if (!($plugin instanceof Plugin)) {
           throw new Exception('All plugins must extend Solarium\Core\Plugin');
        }

        $plugin->initPlugin($this, $options);

        $this->pluginInstances[$key] = $plugin;

        return $this;
    }

    /**
     * Register multiple plugins
     *
     * @param array $plugins
     * @return self Provides fluent interface
     */
    public function registerPlugins($plugins)
    {
        foreach ($plugins as $key => $plugin) {

            if (!isset($plugin['key'])) $plugin['key'] = $key;

            $this->registerPlugin(
                $plugin['key'],
                $plugin['plugin'],
                $plugin['options']
            );
        }

        return $this;
    }

    /**
     * Get all registered plugins
     *
     * @return array
     */
    public function getPlugins()
    {
        return $this->pluginInstances;
    }

    /**
     * Get a plugin instance
     *
     * @param string $key
     * @param boolean $autocreate
     * @return Plugin|null
     */
    public function getPlugin($key, $autocreate = true)
    {
        if (isset($this->pluginInstances[$key])) {
            return $this->pluginInstances[$key];
        } elseif ($autocreate) {
            if (array_key_exists($key, $this->pluginTypes)) {
                $this->registerPlugin($key, $this->pluginTypes[$key]);
                return $this->pluginInstances[$key];
            } else {
                throw new Exception('Cannot autoload plugin of unknown type: ' . $key);
            }
        } else {
            return null;
        }
    }

    /**
     * Remove a plugin instance
     *
     * You can remove a plugin by passing the plugin key, or the plugin instance
     *
     * @param string|Plugin $plugin
     * @return self Provides fluent interface
     */
    public function removePlugin($plugin)
    {
        if (is_object($plugin)) {
            foreach ($this->pluginInstances as $key => $instance) {
                if ($instance === $plugin) {
                    unset($this->pluginInstances[$key]);
                    break;
                }
            }
        } else {
            if (isset($this->pluginInstances[$plugin])) {
                unset($this->pluginInstances[$plugin]);
            }
        }
        return $this;
    }

    /**
     * Trigger external events for plugins
     *
     * This methods adds 'namespacing' to the event name to prevent conflicts with Solariums internal event keys.
     *
     * Based on the event name you can always tell if an event was internal (Solarium base classes)
     * or external (plugins, even if it's a plugin included with Solarium).
     *
     * External events always have the 'event' prefix in the event name.
     *
     * @param string $event
     * @param array $params
     * @param bool $resultOverride
     * @return void|mixed
     */
    public function triggerEvent($event, $params = array(), $resultOverride = false)
    {
        // Add namespacing
        $event = 'event'.$event;

        return $this->callPlugins($event, $params, $resultOverride);
    }

    /**
     * Forward events to plugins
     *
     * @param string $event
     * @param array $params
     * @param bool $resultOverride
     * @return void|mixed
     */
    protected function callPlugins($event, $params, $resultOverride = false)
    {
        foreach ($this->pluginInstances AS $plugin) {
            if (method_exists($plugin, $event)) {
                $result = call_user_func_array(array($plugin, $event), $params);

                if ($result !== null && $resultOverride) {
                    return $result;
                }
            }
        }
    }

    /**
     * Creates a request based on a query instance
     *
     * @param Query $query
     * @return Request
     */
    public function createRequest($query)
    {
        $pluginResult = $this->callPlugins('preCreateRequest', array($query), true);
        if($pluginResult !== null) return $pluginResult;

        $requestBuilder = $query->getRequestBuilder();
        if (!$requestBuilder) {
            throw new Exception('No requestbuilder returned by querytype: '. $query->getType());
        }

        $request = $requestBuilder->build($query);

        $this->callPlugins('postCreateRequest', array($query, $request));

        return $request;
    }

    /**
     * Creates a result object
     *
     * @param Query $query
     * @param array Response $response
     * @return Result
     */
    public function createResult($query, $response)
    {
        $pluginResult = $this->callPlugins('preCreateResult', array($query, $response), true);
        if($pluginResult !== null) return $pluginResult;

        $resultClass = $query->getResultClass();
        $result = new $resultClass($this, $query, $response);

        $this->callPlugins('postCreateResult', array($query, $response, $result));

        return $result;
    }

    /**
     * Execute a query
     *
     * @param Query
     * @return Result
     */
    public function execute($query)
    {
        $pluginResult = $this->callPlugins('preExecute', array($query), true);
        if($pluginResult !== null) return $pluginResult;

        $request = $this->createRequest($query);
        $response = $this->executeRequest($request);
        $result = $this->createResult($query, $response);

        $this->callPlugins('postExecute', array($query, $result));

        return $result;
    }

    /**
     * Execute a request and return the response
     *
     * @param Request
     * @return Response
     */
    public function executeRequest($request)
    {
        $pluginResult = $this->callPlugins('preExecuteRequest', array($request), true);
        if ($pluginResult !== null) {
            $response = $pluginResult; //a plugin result overrules the standard execution result
        } else {
            $response = $this->getAdapter()->execute($request);
        }

        $this->callPlugins('postExecuteRequest', array($request, $response));

        return $response;
    }

    /**
     * Execute a ping query
     *
     * Example usage:
     * <code>
     * $client = new Solarium\Client;
     * $query = $client->createPing();
     * $result = $client->ping($query);
     * </code>
     *
     * @see Solarium\Query\Ping
     *
     * @internal This is a convenience method that forwards the query to the
     *  execute method, thus allowing for an easy to use and clean API.
     *
     * @param Solarium\Query\Ping\Query $query
     * @return Solarium\Query\Ping\Result
     */
    public function ping($query)
    {
        return $this->execute($query);
    }

    /**
     * Execute an update query
     *
     * Example usage:
     * <code>
     * $client = new Solarium\Client;
     * $query = $client->createUpdate();
     * $update->addOptimize();
     * $result = $client->update($update);
     * </code>
     *
     * @see Solarium\Query\Update
     * @see Solarium\Result\Update
     *
     * @internal This is a convenience method that forwards the query to the
     *  execute method, thus allowing for an easy to use and clean API.
     *
     * @param Solarium\Query\Update\Query $query
     * @return Solarium\Query\Update\Result
     */
    public function update($query)
    {
        return $this->execute($query);
    }

    /**
     * Execute a select query
     *
     * Example usage:
     * <code>
     * $client = new Solarium\Client;
     * $query = $client->createSelect();
     * $result = $client->select($query);
     * </code>
     *
     * @see Solarium\Query\Select
     * @see Solarium\Result\Select
     *
     * @internal This is a convenience method that forwards the query to the
     *  execute method, thus allowing for an easy to use and clean API.
     *
     * @param Solarium\Query\Query\Select\Query $query
     * @return Solarium\Query\Result\Select\Result
     */
    public function select($query)
    {
        return $this->execute($query);
    }

    /**
     * Execute a MoreLikeThis query
     *
     * Example usage:
     * <code>
     * $client = new Solarium\Client;
     * $query = $client->createMoreLikeThis();
     * $result = $client->moreLikeThis($query);
     * </code>
     *
     * @see Solarium\Query\MoreLikeThis
     * @see Solarium\Result\MoreLikeThis
     *
     * @internal This is a convenience method that forwards the query to the
     *  execute method, thus allowing for an easy to use and clean API.
     *
     * @param Solarium\Query\MoreLikeThis\Query $query
     * @return Solarium\Query\MoreLikeThis\Result
     */
    public function moreLikeThis($query)
    {
        return $this->execute($query);
    }

    /**
     * Execute an analysis query
     *
     * @internal This is a convenience method that forwards the query to the
     *  execute method, thus allowing for an easy to use and clean API.
     *
     * @param Solarium\Query\Analysis\Query\Document|Solarium\Query\Analysis\Query\Field $query
     * @return Solarium\Query\Analysis\Result\Document|Solarium\Query\Analysis\Result\Field
     */
    public function analyze($query)
    {
        return $this->execute($query);
    }

    /**
     * Execute a terms query
     *
     * @internal This is a convenience method that forwards the query to the
     *  execute method, thus allowing for an easy to use and clean API.
     *
     * @param Solarium\Query\Terms\Query $query
     * @return Solarium\Query\Terms\Result
     */
    public function terms($query)
    {
        return $this->execute($query);
    }

    /**
     * Execute a suggester query
     *
     * @internal This is a convenience method that forwards the query to the
     *  execute method, thus allowing for an easy to use and clean API.
     *
     * @param Solarium\Query\Suggester\Query $query
     * @return Solarium\Query\Suggester\Result
     */
    public function suggester($query)
    {
        return $this->execute($query);
    }

    /**
     * Create a query instance
     *
     * @param string $type
     * @param array $options
     * @return Solarium\Query
     */
    public function createQuery($type, $options = null)
    {
        $type = strtolower($type);

        $pluginResult = $this->callPlugins('preCreateQuery', array($type, $options), true);
        if($pluginResult !== null) return $pluginResult;

        if (!isset($this->queryTypes[$type])) {
            throw new Exception('Unknown querytype: '. $type);
        }

        $class = $this->queryTypes[$type];
        $query = new $class($options);

        $this->callPlugins('postCreateQuery', array($type, $options, $query));

        return $query;
    }

    /**
     * Create a select query instance
     *
     * @param mixed $options
     * @return Solarium\Query\Select\Query\Query
     */
    public function createSelect($options = null)
    {
        return $this->createQuery(self::QUERY_SELECT, $options);
    }

    /**
     * Create a MoreLikeThis query instance
     *
     * @param mixed $options
     * @return Solarium\Query\MorelikeThis\Query
     */
    public function createMoreLikeThis($options = null)
    {
        return $this->createQuery(self::QUERY_MORELIKETHIS, $options);
    }

    /**
     * Create an update query instance
     *
     * @param mixed $options
     * @return Solarium\Query\Update\Query
     */
    public function createUpdate($options = null)
    {
        return $this->createQuery(self::QUERY_UPDATE, $options);
    }

    /**
     * Create a ping query instance
     *
     * @param mixed $options
     * @return Solarium\Query\Ping\Query
     */
    public function createPing($options = null)
    {
        return $this->createQuery(self::QUERY_PING, $options);
    }

    /**
     * Create an analysis field query instance
     *
     * @param mixed $options
     * @return Solarium\Query\Analysis\Query\Field
     */
    public function createAnalysisField($options = null)
    {
        return $this->createQuery(self::QUERY_ANALYSIS_FIELD, $options);
    }

    /**
     * Create an analysis document query instance
     *
     * @param mixed $options
     * @return Solarium\Query\Analysis\Query\Document
     */
    public function createAnalysisDocument($options = null)
    {
        return $this->createQuery(self::QUERY_ANALYSIS_DOCUMENT, $options);
    }

    /**
     * Create a terms query instance
     *
     * @param mixed $options
     * @return Solarium\Query\Terms\Query
     */
    public function createTerms($options = null)
    {
        return $this->createQuery(self::QUERY_TERMS, $options);
    }

    /**
     * Create a suggester query instance
     *
     * @param mixed $options
     * @return Solarium\Query\Suggester\Query
     */
    public function createSuggester($options = null)
    {
        return $this->createQuery(self::QUERY_SUGGESTER, $options);
    }
}