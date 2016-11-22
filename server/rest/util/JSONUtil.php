<?php

/**
 * Created by PhpStorm.
 * User: joeyblack
 * Date: 11/22/16
 * Time: 4:43 PM
 *
 * /**
 * Serialize a simple PHP object into json
 * Should be used for POPO that has getter methods for the relevant properties to serialize
 * A property can be simple or by itself another POPO object
 *
 *
 */
class JSONUtil
{
    /**
     * Local cache of a property getters per class - optimize reflection code if the same object appears several times
     * @var array
     */
    private $classPropertyGetters = array();

    /**
     * @param mixed $object
     * @return string
     */
    public function serialize($object){
        if (is_string($object)){
            return json_encode($object);
        }else {
            return json_encode($this->serializeInternal($object));
        }
    }

    /**
     * @param $object
     * @return array
     */
    private function serializeInternal($object){
        if (is_array($object)) {
            $result = $this->serializeArray($object);
        } elseif (is_object($object)) {
            $result = $this->serializeObject($object);
        } else {
            $result = $object;
        }
        return $result;
    }

    /**
     * @param $object
     * @return \ReflectionClass
     */
    private function getClassPropertyGetters($object){
        $className = get_class($object);
        $parentClassName = get_parent_class($object);
        if (!isset($this->classPropertyGetters[$className])) {
            $reflector = new \ReflectionClass($className);
            $properties = $reflector->getProperties();
            $getters = array();
            foreach ($properties as $property)
            {
                $name = $property->getName();
                $getter = "get" . ucfirst($name);
                try {
                    $reflector->getMethod($getter);
                    $getters[$name] = $getter;
                } catch (\Exception $e) {
                    // if no getter for a specific property - ignore it
                }
            }
            if ($parentClassName != null && $parentClassName == "Entity"){
                $parentReflector = new \ReflectionClass($parentClassName);
                $parentProperties = $parentReflector->getProperties();
                foreach ($parentProperties as $property)
                {
                    $name = $property->getName();
                    $getter = "get" . ucfirst($name);
                    try {
                        $reflector->getMethod($getter);
                        $getters[$name] = $getter;
                    } catch (\Exception $e) {
                        // if no getter for a specific property - ignore it
                    }
                }
            }
            $this->classPropertyGetters[$className] = $getters;
        }
        return $this->classPropertyGetters[$className];
    }

    /**
     * @param $object
     * @return array
     */
    private function serializeObject($object) {
        $properties = $this->getClassPropertyGetters($object);
        $data = array();
        foreach ($properties as $name => $property)
        {
            $data[$name] = $this->serializeInternal($object->$property());
        }
        return $data;
    }

    /**
     * @param $array
     * @return array
     */
    private function serializeArray($array){
        $result = array();
        foreach ($array as $key => $value) {
            $result[$key] = $this->serializeInternal($value);
        }
        return $result;
    }
}