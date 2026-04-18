<?php

namespace App\Http\Interfaces;

use Exception;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Collection;
use JsonSerializable;

abstract class DataInterface implements Jsonable, JsonSerializable, Arrayable
{

    /**
     * Validate if the data is a valid instance of DataInterface or a collection of it.
     *
     * @param mixed $data
     * @throws Exception
     */
    public static function validate(mixed $data): void
    {
        if (!self::isValid($data)) {
            throw new Exception("Response data type must be a DataInterface class or a class that extends DataInterface");
        }
    }

    /**
     * Check if the data is valid according to DataInterface rules.
     *
     * @param mixed $data
     * @return bool
     */
    private static function isValid(mixed $data): bool
    {
        if ($data instanceof Collection) {
            return self::isValidCollection($data);
        }
        if (is_array($data)) {
            return self::isValidArray($data);
        }
        return self::isValidObject($data);
    }

    /**
     * Check if all elements in the collection are instances of DataInterface.
     *
     * @param Collection $collection
     * @return bool
     */
    public static function isValidCollection(Collection $collection): bool
    {
        return $collection->every(function ($item): bool {
            return $item instanceof DataInterface;
        });
    }

    /**
     * Check if all elements in the array are instances of DataInterface.
     *
     * @param array $array
     * @return bool
     */
    public static function isValidArray(array $array): bool
    {
        foreach ($array as $item) {
            if (!$item instanceof DataInterface) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if the object is an instance of DataInterface.
     *
     * @param mixed $object
     * @return bool
     */
    public static function isValidObject(mixed $object): bool
    {
        return $object instanceof DataInterface;
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param int $options
     * @return string
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @link https://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return array
     */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }

    /**
     * Get the instance as an array.
     *
     * @return array|null
     */
    public function toArray(): ?array
    {
        return self::convertToArray($this);
    }

    /**
     * Convert an object or array to an array recursively.
     *
     * @param mixed $object
     * @return array|null
     */
    public static function convertToArray(mixed $object): ?array
    {
        if (is_array($object) || is_object($object)) {
            $result = [];
            foreach ($object as $key => $value) {
                $result[$key] = is_array($value) || is_object($value) ? self::convertToArray($value) : $value;
            }
            return (is_object($object) && empty($result)) ? null : $result;
        }
        return null;
    }

}
