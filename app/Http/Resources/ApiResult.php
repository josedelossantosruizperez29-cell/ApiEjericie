<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiResult extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return ['success' => true, 'message' => 'Operación realizada correctamente', 'data' => $this->resource];
    }
}
