<?php

namespace Nuwave\Lighthouse\Schema\Registrars;

use Illuminate\Support\Collection;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\InterfaceType;
use Nuwave\Lighthouse\Support\Interfaces\Connection;
use Nuwave\Lighthouse\Support\Definition\RelayConnectionType;
use Nuwave\Lighthouse\Support\Definition\Fields\ConnectionField;

class ConnectionRegistrar extends BaseRegistrar
{
    /**
     * Collection of registered type instances.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $instances;

    /**
     * Create new instance of connection registrar.
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->instances = new Collection;
    }

    /**
     * Add type to registrar.
     *
     * @param  string  $name
     * @param  array  $field
     * @return array
     */
    public function register($name, $field)
    {
        $this->collection->put($name, $field);

        return $field;
    }

    /**
     * Get instance of connection type.
     *
     * @param  string  $name
     * @param  string|null  $parent
     * @param  bool  $fresh
     * @return \Nuwave\Lighthouse\Support\Definition\Fields\ConnectionField
     */
    public function instance($name, $parent = null, $fresh = false)
    {
        $instanceName = $this->instanceName($name);
        $typeName = $this->typeName($name);

        if (! $fresh && $this->instances->has($instanceName)) {
            return $this->instances->get($instanceName);
        }

        $key = $parent ? $parent.'.'.$instanceName : $instanceName;
        $nodeType = $this->getSchema()->typeInstance($typeName);
        $instance = $this->getInstance($name, $nodeType);

        $this->instances->put($key, $instance);

        return $instance;
    }

    /**
     * Generate connection field.
     *
     * @param  string  $name
     * @param  \GraphQL\Type\Definition\ObjectType  $nodeType
     * @return array
     */
    public function getInstance($name, ObjectType $nodeType)
    {
        $isConnection = $name instanceof Connection;
        $connection = new RelayConnectionType();
        $instanceName = $this->instanceName($name);
        $connectionName = (! preg_match('/Connection$/', $instanceName)) ? $instanceName.'Connection' : $instanceName;
        $connection->setName(studly_case($connectionName));

        if ($isConnection && is_callable([$name, 'description'])) {
            $connection->setDescription($name->description());
        }

        $pageInfoType = $this->getSchema()->typeInstance('pageInfo');
        $edgeType = $this->getSchema()->edgeInstance($instanceName, $nodeType);

        $connection->setEdgeType($edgeType);
        $connection->setPageInfoType($pageInfoType);
        $instance = $connection->toType();

        $field = new ConnectionField([
            'args'    => $isConnection ? array_merge($name->args(), RelayConnectionType::connectionArgs()) : RelayConnectionType::connectionArgs(),
            'type'    => $instance,
            'resolve' => $isConnection ? [$name, 'resolve'] : null,
        ]);

        if ($connection->interfaces) {
            InterfaceType::addImplementationToInterfaces($instance);
        }

        return $field;
    }

    /**
     * Extract name.
     *
     * @param  mixed  $name
     * @return string
     */
    protected function instanceName($name)
    {
        if ($name instanceof Connection) {
            return strtolower(snake_case((str_replace('\\', '_', $name->name()))));
        }

        return $name;
    }

    /**
     * Extract name.
     *
     * @param  mixed  $name
     * @return string
     */
    protected function typeName($name)
    {
        if ($name instanceof Connection) {
            return $name->type();
        }

        return $name;
    }
}
