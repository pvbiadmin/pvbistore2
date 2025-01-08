<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static encode($id)
 */
class Hashids extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Hashids\Hashids::class;
    }
}
