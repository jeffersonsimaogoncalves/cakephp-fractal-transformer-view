<?php
/**
 * Created by PhpStorm.
 * User: jeffersonsimaogoncalves
 * Date: 25/05/2018
 * Time: 23:45
 */

namespace FractalTransformerView\Utils;

use Cake\Datasource\EntityInterface;
use Cake\ORM\Query;
use Cake\ORM\ResultSet;
use Exception;
use FractalTransformerView\Serializer\ArraySerializer;
use League\Fractal\Manager;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use League\Fractal\TransformerAbstract;

/**
 * Class TransformerData
 *
 * @property \League\Fractal\TransformerAbstract transform
 *
 * @package FractalTransformerView\Utils
 */
class TransformerData
{
    /**
     * @param $data
     * @param $transform
     * @return array
     * @throws \Exception
     */
    public function getData($data, $transform)
    {
        $this->transform = $transform;
        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());

        if (is_array($data)) {
            foreach ($data as $varName => &$var) {
                $var = $this->transform($manager, $var);
            }
            unset($var);
        } else {
            $data = $this->transform($manager, $data);
        }

        return $data;
    }

    /**
     * @param \League\Fractal\Manager $manager
     * @param $var
     * @return array
     * @throws \Exception
     */
    protected function transform(Manager $manager, $var)
    {
        if (!$transformer = $this->getTransformer()) {
            return $var;
        }

        if (is_array($var) || $var instanceof Query || $var instanceof ResultSet) {
            $resource = new Collection($var, $transformer, 'data');
        } elseif ($var instanceof EntityInterface) {
            $resource = new Item($var, $transformer);
        } else {
            throw new Exception('Unserializable variable');
        }

        return $manager->createData($resource)->toArray();
    }

    /**
     * @return bool|\League\Fractal\TransformerAbstract
     * @throws \Exception
     */
    protected function getTransformer()
    {
        $transformerClass = $this->transform;

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
                    'Transformer class not instance of TransformerAbstract: %s', $transformerClass
                )
            );
        }

        return $transformer;
    }

    /**
     * @param $var
     * @return bool|string
     */
    protected function getTransformerClass($var)
    {
        $entity = null;
        if ($var instanceof Query) {
            $entity = $var->repository()->newEntity();
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
        $transformerClass = str_replace('\\Model\\Entity\\', '\\Model\\Transformer\\', $entityClass) . 'Transformer';

        if (!class_exists($transformerClass)) {
            return false;
        }

        return $transformerClass;
    }
}
