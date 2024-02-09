<?php

namespace DataTable;

use Illuminate\Http\Resources\Json\ResourceCollection;

class GeneralCollection extends ResourceCollection
{
    // For keeping keys in arrays. When false then keys will be lost if indexes are numeric value.
    protected $preserveKeys = true;
    
}
