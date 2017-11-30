<?php

namespace Mpociot\ApiDoc\Tests\Fixtures;

use Illuminate\Http\Resources\Json\Resource;

class TestStaticMessageResponse extends Resource
{
    /**
     * {@inheritDoc}
     */
    public function toArray($request)
    {
        return [
            'message' => 'test',
            'status_code' => '200',
        ];
    }
}
