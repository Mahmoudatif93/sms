<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Model;

class CountHelper
{
    /**
     * Get the count of records from a model based on optional conditions.
     *
     * @param string $modelClass The fully qualified class name of the model.
     * @param array|null $conditions Optional key-value pairs for filtering records.
     * @param callable|null $queryModifier Optional callback for additional query modifications.
     * @return int
     */
    public static function getCount(string $modelClass, array $conditions = null, callable $queryModifier = null): int
    {
        // Ensure the model class exists and is valid
        if (!class_exists($modelClass)) {
            throw new \InvalidArgumentException("Model class {$modelClass} does not exist.");
        }

        // Create a new model instance
        $model = new $modelClass;

        // Ensure the instance is of type Model
        if (!$model instanceof Model) {
            throw new \InvalidArgumentException("{$modelClass} is not a valid Eloquent model.");
        }

        // Start the query
        $query = $model->newQuery();

        // Apply conditions if provided
        if ($conditions && is_array($conditions)) {
            $query->where($conditions);
        }

        // Apply additional query modifications if provided
        if ($queryModifier) {
            $query = $queryModifier($query);
        }

        // Return the count of records
        return $query->count();
    }
}
