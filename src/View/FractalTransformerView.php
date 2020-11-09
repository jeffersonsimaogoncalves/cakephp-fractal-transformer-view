<?php

namespace FractalTransformerView\View;

use Cake\Datasource\EntityInterface;
use Cake\Event\EventManager;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\ORM\Query;
use Cake\ORM\ResultSet;
use Cake\Utility\Hash;
use Cake\View\JsonView;
use Exception;
use FractalTransformerView\Serializer\ArraySerializer;
use League\Fractal\Manager;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use League\Fractal\Serializer\SerializerAbstract;
use League\Fractal\TransformerAbstract;

/**
 * Class FractalTransformerView
 *
 * @package FractalTransformerView\View
 */
class FractalTransformerView extends JsonView
{
    /**
     * @var \League\Fractal\Serializer\SerializerAbstract
     */
    protected $_serializer;
    /**
     * @var array|mixed
     */
    private $_specialVars;

    /**
     * Constructor
     *
     * @param  \Cake\Http\ServerRequest|null  $request  Request instance.
     * @param  \Cake\Http\Response|null  $response  Response instance.
     * @param  \Cake\Event\EventManager|null  $eventManager  EventManager instance.
     * @param  array  $viewOptions  An array of view options
     */
    public function __construct(
        ServerRequest $request = null,
        Response $response = null,
        EventManager $eventManager = null,
        array $viewOptions = []
    ) {
        if (isset($viewOptions['serializer'])) {
            $this->setSerializer($viewOptions['serializer']);
        }

        parent::__construct($request, $response, $eventManager, $viewOptions);

        $this->_specialVars[] = '_transform';
        $this->_specialVars[] = '_resourceKey';
        $this->_specialVars[] = '_includes';
    }

    /**
     * Returns data to be serialized.
     *
     * @param  array|string|bool  $serialize  The name(s) of the view variable(s) that
     *                                     need(s) to be serialized. If true all available view variables will be used.
     *
     * @return mixed The data to serialize.
     * @throws \Exception
     */
    protected function _dataToSerialize($serialize = true)
    {
        $data = parent::_dataToSerialize($serialize);

        $serializer = $this->getSerializer();
        $includes = $this->get('_includes');
        $manager = new Manager();
        $manager->setSerializer($serializer);

        if ($includes) {
            $manager->parseIncludes($includes);
        }

        if (is_array($data)) {
            foreach ($data as $varName => &$var) {
                $var = $this->transform($manager, $var, $varName);
            }
            unset($var);
        } else {
            $data = $this->transform($manager, $data);
        }

        return $data;
    }

    /**
     * Get the currently set serializer instance, or return the default ArraySerializer
     *
     * @return \League\Fractal\Serializer\SerializerAbstract
     */
    public function getSerializer()
    {
        if (empty($this->_serializer)) {
            return new ArraySerializer();
        }

        return $this->_serializer;
    }

    /**
     * Sets the serializer
     *
     * @param  \League\Fractal\Serializer\SerializerAbstract|null  $serializer  Serializer to use
     *
     * @return void
     */
    public function setSerializer(SerializerAbstract $serializer = null)
    {
        $this->_serializer = $serializer;
    }

    /**
     * Transform var using given manager
     *
     * @param  Manager  $manager  Fractal manager
     * @param  mixed  $var  variable
     * @param  bool  $varName  variable name
     *
     * @return array
     * @throws Exception
     */
    protected function transform(Manager $manager, $var, $varName = false)
    {
        if (!$transformer = $this->getTransformer($var, $varName)) {
            return $var;
        }

        if (is_array($var) || $var instanceof Query || $var instanceof ResultSet) {
            $resource = new Collection($var, $transformer, $this->get('_resourceKey'));
        } elseif ($var instanceof EntityInterface) {
            $resource = new Item($var, $transformer, $this->get('_resourceKey'));
        } else {
            throw new Exception('Unserializable variable');
        }

        return $manager->createData($resource)->toArray();
    }

    /**
     * Get transformer for given var
     *
     * @param  mixed  $var  variable
     * @param  bool  $varName  variable name
     *
     * @return bool|\League\Fractal\TransformerAbstract
     * @throws Exception
     */
    protected function getTransformer($var, $varName = false)
    {
        $_transform = $this->get('_transform');
        $transformerClass = $varName
            ? Hash::get((array) $_transform, $varName)
            : $_transform;

        if (is_null($transformerClass)) {
            $transformerClass = $this->getTransformerClass($var);
        }

        if ($transformerClass === false) {
            return false;
        }

        if (!class_exists($transformerClass)) {
            throw new Exception(sprintf('Invalid Transformer class: %s', $transformerClass));
        }

        $transformer = new $transformerClass;
        if (!($transformer instanceof TransformerAbstract)) {
            throw new Exception(
                sprintf(
                    'Transformer class not instance of TransformerAbstract: %s',
                    $transformerClass
                )
            );
        }

        return $transformer;
    }

    /**
     * Get transform class name for given var by figuring out which entity it belongs to. Return FALSE otherwise
     *
     * @param  mixed  $var  variable
     *
     * @return bool|string
     */
    protected function getTransformerClass($var)
    {
        $entity = null;
        if ($var instanceof Query) {
            $entity = $var->getRepository()->newEmptyEntity();
        } elseif ($var instanceof ResultSet) {
            $entity = $var->first();
        } elseif ($var instanceof EntityInterface) {
            $entity = $var;
        } elseif (is_array($var)) {
            $entity = reset($var);
        }

        if (!$entity || !is_object($entity)) {
            return false;
        }

        $entityClass = get_class($entity);
        $transformerClass = str_replace('\\Model\\Entity\\', '\\Model\\Transformer\\', $entityClass).'Transformer';

        if (!class_exists($transformerClass)) {
            return false;
        }

        return $transformerClass;
    }
}
