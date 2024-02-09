<?php

namespace DataTable;

use Illuminate\Http\Resources\Json\ResourceCollection;

class GeneralCollection extends ResourceCollection
{
    // For keeping keys in arrays. When false then keys will be lost if indexes are numeric value.
    protected $preserveKeys = true;
    
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return parent::toArray($request);
    }
}
