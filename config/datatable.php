<?php

return [
    /**
     * Her you can define custom resource for specific data. 
     * Output needs to implement IDatabaseResource or ICollectionResource
     */
    'resources' => [
        'grid' => DataTable\Resources\Grid::class,
        'api' => DataTable\Resources\Api::class,
        'collection' => DataTable\Resources\Collection::class
    ],
    /**
     * Resource output
     * https://laravel.com/docs/9.x/eloquent-resources
     */
    'collection' => DataTable\GeneralCollection::class,
    /**
     * determines if the requested relations have to be in "with" parameter or not
     */
    'strict_with_mode' => true,
    /**
     * if true, then spatie translatable fields will be searched in app language, see https://spatie.be/docs/laravel-translatable/v6/introduction
     */ 
    'allow_translatable' => true
];
