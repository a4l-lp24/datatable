<?php

namespace DataTable;

use Illuminate\Http\Resources\Json\ResourceCollection;

class GeneralCollection extends ResourceCollection {
    protected $preserveKeys = true;

    public function toArray($request): array { 
        return [
            'data' => $this->collection,
            'links' => []
        ];
    }
}